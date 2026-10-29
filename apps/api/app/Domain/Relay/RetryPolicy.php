<?php

namespace App\Domain\Relay;

class RetryPolicy
{
    public static function classify(?int $status, ?string $error): string
    {
        if ($error) {
            return 'uncertain';
        }
        if ($status >= 200 && $status < 300) {
            return 'delivered';
        }
        if ($status === 429) {
            return 'rate_limited';
        }
        if ($status === 408 || ($status >= 500 && $status <= 599)) {
            return 'retryable';
        }

        return 'terminal';
    }

    public static function delay(int $attempt, ?string $retryAfter = null): int
    {
        $base = min(60, 2 ** min($attempt, 6)) + random_int(0, 2);
        if ($retryAfter !== null) {
            $seconds = ctype_digit($retryAfter) ? (int) $retryAfter : max(0, (strtotime($retryAfter) ?: time()) - time());
            $base = max($base, min(120, $seconds));
        }

        return min(120, $base);
    }
}
