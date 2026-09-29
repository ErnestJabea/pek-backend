<?php

namespace App\Services\Payments;

use RuntimeException;

final class S3pTransactionNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Aucune transaction retournée par Maviance pour cette référence.');
    }
}
