<?php

namespace App\Domain\Relay;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class Mapper
{
    public static function validate(array $spec): void
    {
        if (count($spec) > 30) {
            throw new InvalidArgumentException('A mapping can contain at most 30 output fields.');
        }
        foreach ($spec as $key => $rule) {
            if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,60}$/D', (string) $key) || ! is_array($rule)) {
                throw new InvalidArgumentException('Mapping output keys must be plain field names.');
            }
            if (array_diff(array_keys($rule), ['path', 'default', 'transform', 'literal'])) {
                throw new InvalidArgumentException('Unknown mapping option.');
            }
            if (isset($rule['path']) && (! is_string($rule['path']) || ! preg_match('/^[a-zA-Z0-9_.]{1,160}$/D', $rule['path']))) {
                throw new InvalidArgumentException('Invalid mapping source path.');
            }
            if (! in_array($rule['transform'] ?? 'identity', ['identity', 'upper', 'lower', 'string'], true)) {
                throw new InvalidArgumentException('Unsupported mapping transform.');
            }
            if (strlen(json_encode($rule)) > 4000) {
                throw new InvalidArgumentException('Mapping rule is too large.');
            }
        }
    }

    public static function apply(array $payload, ?array $spec): array
    {
        if (! $spec) {
            return $payload;
        }
        self::validate($spec);
        $out = [];
        foreach ($spec as $key => $rule) {
            $value = array_key_exists('literal', $rule) ? $rule['literal'] : Arr::get($payload, $rule['path'] ?? $key, $rule['default'] ?? null);
            if ($value === null) {
                throw new InvalidArgumentException('Required mapping source field is missing: '.$key);
            }
            $transform = $rule['transform'] ?? 'identity';
            if ($transform !== 'identity' && ! is_scalar($value)) {
                throw new InvalidArgumentException('Mapping transform expects a scalar.');
            }
            $out[$key] = match ($transform) {
                'upper' => mb_strtoupper((string) $value), 'lower' => mb_strtolower((string) $value), 'string' => (string) $value, default => $value
            };
        }

        return $out;
    }
}
