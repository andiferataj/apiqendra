<?php

namespace App\Domain\Relay;

class Signature
{
    public static function sign(string $body, string $secret, int $timestamp): string
    {
        return 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function verify(string $body, string $secret, string $signature, string $timestamp, int $now): bool
    {
        return ctype_digit($timestamp) && abs($now - (int) $timestamp) <= 300 && hash_equals(self::sign($body, $secret, (int) $timestamp), $signature);
    }

    public static function github(string $body, string $secret, string $signature): bool
    {
        return hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $signature);
    }
}
