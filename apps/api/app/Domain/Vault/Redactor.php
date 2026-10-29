<?php

namespace App\Domain\Vault;

class Redactor
{
    public static function clean(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 15) {
            return '[depth limit]';
        }
        if (is_array($value)) {
            $result = [];
            foreach (array_slice($value, 0, 200, true) as $key => $item) {
                $result[$key] = preg_match('/password|secret|token|authorization|cookie|api.?key|email|phone|address|credit|ssn/i', (string) $key) ? '[redacted]' : self::clean($item, $depth + 1);
            }

            return $result;
        }
        if (is_string($value)) {
            return mb_substr($value, 0, 4000);
        }

        return $value;
    }
}
