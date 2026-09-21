<?php

namespace App\Services\Payments;

use Symfony\Component\Process\Process;

class ProofScanner
{
    public function scan(string $path): string
    {
        $binary = config('payments.scanner_binary');
        if (! $binary || ! is_file($binary)) {
            return 'quarantined';
        }
        try {
            $process = new Process([$binary, '--no-summary', '--', $path]);
            $process->setTimeout(45)->run();

            return match ($process->getExitCode()) {
                0 => 'clean', 1 => 'infected', default => 'quarantined'
            };
        } catch (\Throwable) {
            return 'quarantined';
        }
    }
}
