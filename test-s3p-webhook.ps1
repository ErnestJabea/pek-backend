# Script de test et simulation de Webhook S3P Maviance
[CmdletBinding()]
param(
    [string]$Url = "http://127.0.0.1:8000/api/s3p/webhook",
    [string]$Trid = "TEST-REF-" + (Get-Random -Minimum 1000 -Maximum 9999),
    [string]$Status = "SUCCESS",
    [int]$ErrorCode = 0,
    [string]$Ptn = "PTN" + (Get-Date -Format "yyyyMMddHHmmss")
)

# Lire le secret S3P depuis .env
$envPath = Join-Path $PSScriptRoot ".env"
$secret = ""
if (Test-Path $envPath) {
    $line = Get-Content $envPath | Select-String "^S3P_WEBHOOK_SECRET="
    if ($line) {
        $secret = ($line -split "=", 2)[1].Trim("'").Trim('"')
    }
}

if (-not $secret) {
    Write-Host "[!] Erreur: S3P_WEBHOOK_SECRET introuvable dans .env" -ForegroundColor Red
    exit 1
}

$timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
$deliveryId = [guid]::NewGuid().ToString()

$payloadObj = [ordered]@{
    trid = $Trid
    timestamp = $timestamp
    status = $Status
    errorCode = $ErrorCode
}
$jsonBody = $payloadObj | ConvertTo-Json -Compress

# Calcul de la signature HMAC-SHA1 sur le corps brut
$hmac = New-Object System.Security.Cryptography.HMACSHA1
$hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($secret)
$hashBytes = $hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($jsonBody))
$signature = ($hashBytes | ForEach-Object { "{0:x2}" -f $_ }) -join ""

Write-Host "Envoi du Webhook Maviance simule vers : $Url" -ForegroundColor Cyan
Write-Host "  TRID : $Trid" -ForegroundColor Gray
Write-Host "  PTN  : $Ptn" -ForegroundColor Gray
Write-Host "  Etat : $Status (Code: $ErrorCode)" -ForegroundColor Gray
Write-Host "  Signature HMAC-SHA1 : $signature" -ForegroundColor Gray

try {
    $headers = @{
        "Content-Type" = "application/json"
        "X-Signature" = $signature
        "X-Ptn" = $Ptn
        "X-Delivery" = $deliveryId
    }

    $response = Invoke-RestMethod -Uri $Url -Method Post -Headers $headers -Body $jsonBody
    Write-Host "[OK] Reponse HTTP 200 Recue :" -ForegroundColor Green
    Write-Host ($response | ConvertTo-Json) -ForegroundColor Green
    Write-Host ""
    Write-Host "Pour traiter ce callback dans la base de donnees, lancez :" -ForegroundColor Yellow
    Write-Host "php artisan payments:reconcile" -ForegroundColor Cyan
} catch {
    Write-Host "[ERR] Echec envoi requete :" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    if ($_.Exception.Response) {
        $stream = $_.Exception.Response.GetResponseStream()
        if ($stream) {
            $reader = New-Object System.IO.StreamReader($stream)
            Write-Host $reader.ReadToEnd() -ForegroundColor Red
        }
    }
}
