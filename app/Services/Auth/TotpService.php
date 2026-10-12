<?php

namespace App\Services\Auth;

class TotpService
{
    private static string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Génère une clé secrète Base32 aléatoire sécurisée.
     */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        $max = strlen(self::$base32Chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, $max)];
        }

        return $secret;
    }

    /**
     * Construit l'URL standard otpauth:// pour Google Authenticator, Microsoft Authenticator, etc.
     */
    public static function getProvisioningUri(string $secret, string $email, string $issuer = 'PEK'): string
    {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($email);

        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Vérifie un code TOTP à 6 chiffres avec tolérance de dérive d'horloge (+/- 30 secondes par défaut).
     */
    public static function verify(string $secret, string $code, int $discrepancy = 1): bool
    {
        $cleanCode = trim($code);
        if (strlen($cleanCode) !== 6 || !ctype_digit($cleanCode)) {
            return false;
        }

        $secretBinary = self::base32Decode($secret);
        if ($secretBinary === false || strlen($secretBinary) === 0) {
            return false;
        }

        $currentTimeSlice = (int) floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $slice = $currentTimeSlice + $i;
            $calculatedCode = self::calculateCode($secretBinary, $slice);
            if (hash_equals($calculatedCode, $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcule le code TOTP pour un intervalle de temps donné.
     */
    private static function calculateCode(string $secretBinary, int $timeSlice): string
    {
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretBinary, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashpart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;
        $modulo = $value % 1000000;

        return str_pad((string) $modulo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Décode une chaîne Base32 en binaire.
     */
    private static function base32Decode(string $b32): string|false
    {
        $b32 = strtoupper(trim($b32));
        $b32 = rtrim($b32, '=');
        $lut = array_flip(str_split(self::$base32Chars));

        $binary = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
            $char = $b32[$i];
            if (!isset($lut[$char])) {
                return false;
            }
            $buffer = ($buffer << 5) | $lut[$char];
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }
}
