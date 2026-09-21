<?php

namespace App\Services\Payments;

use Carbon\CarbonImmutable;
use RuntimeException;

final class S3pTimestamp
{
    public static function parse(mixed $value): CarbonImmutable
    {
        if (!is_string($value)) throw new RuntimeException('Date fournisseur absente.');
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-](?:0\d|1[0-4]):[0-5]\d)$/D', $value)) {
            $date = CarbonImmutable::parse($value);
            if ($date->format('Y-m-d\TH:i:s') !== substr($value, 0, 19)) throw new RuntimeException('Date fournisseur invalide.');
            return $date;
        }
        // Legacy dates are supported only with an explicitly confirmed provider timezone.
        $timezone = config('payments.s3p.timestamp_timezone');
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value)
            && is_string($timezone) && in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
            if ($date && $date->format('Y-m-d H:i:s') === $value) return $date;
        }
        throw new RuntimeException('Date fournisseur invalide ou fuseau non confirmé.');
    }
}
