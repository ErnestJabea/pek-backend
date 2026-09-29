# Script de demarrage du tunnel HTTPS local pour les Webhooks Maviance S3P
[CmdletBinding()]
param(
    [int]$Port = 8000,
    [string]$Tool = "localtunnel" # localtunnel, cloudflared, ngrok, pinggy
)

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  PEK - Tunnel HTTPS pour Webhooks Maviance S3P (Local)   " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Verifier si Laravel ecoute sur le port cible
$portCheck = netstat -ano | findstr ":$Port" | findstr "LISTENING"
if (-not $portCheck) {
    Write-Host "[!] Attention: Aucun service n ecoute sur le port $Port." -ForegroundColor Yellow
    Write-Host "    Pensez a lancer Laravel dans un autre terminal :" -ForegroundColor Yellow
    Write-Host "    php artisan serve --port=$Port" -ForegroundColor Green
    Write-Host ""
} else {
    Write-Host "[OK] Laravel detecte et actif sur le port $Port." -ForegroundColor Green
}

# 2. Recuperer le secret S3P depuis .env si present
$envPath = Join-Path $PSScriptRoot ".env"
$webhookSecret = ""
if (Test-Path $envPath) {
    $line = Get-Content $envPath | Select-String "^S3P_WEBHOOK_SECRET="
    if ($line) {
        $webhookSecret = ($line -split "=", 2)[1].Trim("'").Trim('"')
    }
}

Write-Host ""
Write-Host "Configuration Maviance requise :" -ForegroundColor Magenta
Write-Host "  Secret Webhook configure dans .env : $webhookSecret" -ForegroundColor White
Write-Host "  Endpoint a enregistrer chez Maviance : https://<VOTRE-URL-TUNNEL>/api/s3p/webhook" -ForegroundColor White
Write-Host ""

# 3. Lancer le tunnel selon l outil choisi
if ($Tool -eq "localtunnel") {
    Write-Host "[*] Demarrage de localtunnel via npx sur le port $Port..." -ForegroundColor Cyan
    Write-Host "    (Gardez cette fenetre ouverte tant que vous testez les paiements)" -ForegroundColor DarkGray
    & npx --yes localtunnel --port $Port
} elseif ($Tool -eq "cloudflared") {
    Write-Host "[*] Demarrage de Cloudflare Tunnel..." -ForegroundColor Cyan
    & cloudflared tunnel --url "http://127.0.0.1:$Port"
} elseif ($Tool -eq "ngrok") {
    Write-Host "[*] Demarrage de ngrok..." -ForegroundColor Cyan
    & ngrok http $Port
} elseif ($Tool -eq "pinggy") {
    Write-Host "[*] Demarrage de Pinggy via OpenSSH..." -ForegroundColor Cyan
    & ssh -p 443 -R0:localhost:$Port a.pinggy.io
}
