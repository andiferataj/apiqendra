<?php

namespace App\Domain\Connect;

use App\Domain\Connect\RequestRejected as RuntimeException;

class NetworkPolicy
{
    public function resolve(string $url): array
    {
        $parts = parse_url($url);
        if (! $parts || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || empty($parts['host'])) {
            throw new RuntimeException('URL must have a host and no credentials or fragment.');
        }
        $host = strtolower($parts['host']);
        $scheme = strtolower($parts['scheme'] ?? '');
        if (config('apiqendra.demo') && $host === 'simulator' && $scheme === 'http' && ($parts['port'] ?? 80) === 8090) {
            if (! preg_match('~^/(health|emit|register|github/issues|slack/notify|deliver/(success|503|429|delay|timeout|invalid|recover|unauthorized))$~D', $parts['path'] ?? '')) {
                throw new RuntimeException('Simulator path is not allowlisted.');
            }
            if (isset($parts['query'])) {
                throw new RuntimeException('Simulator query is not allowlisted.');
            }
            $ip = gethostbyname($host);
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new RuntimeException('Simulator DNS unavailable.');
            }

            return ['host' => $host, 'port' => 8090, 'ip' => $ip, 'demo' => true];
        }
        if ($scheme !== 'https' || ($parts['port'] ?? 443) !== 443 || str_contains($host, '%') || str_ends_with($host, '.') || str_contains($host, '[')) {
            throw new RuntimeException('Only HTTPS on port 443 is supported.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            $ips = array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records ?: [])));
        }
        if (! $ips) {
            throw new RuntimeException('Destination DNS did not resolve.');
        }
        foreach ($ips as $ip) {
            if (! self::publicIp($ip)) {
                throw new RuntimeException('Destination resolves to a blocked network.');
            }
        }

        return ['host' => $host, 'port' => 443, 'ip' => $ips[0], 'demo' => false];
    }

    public static function publicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        // Reject IPv4-mapped IPv6 and transitional ranges rather than risk embedded private addresses.
        if (str_contains($ip, ':')) {
            $hex = bin2hex(inet_pton($ip));
            // Compare address bytes, not compressed text (2001::1 omits its zero groups).
            if (str_starts_with($hex, '200100') || str_starts_with($hex, '200101')
                || str_starts_with($hex, '20010db8') || str_starts_with($hex, '2002')
                || str_starts_with($hex, '3fff')) {
                return false;
            }

            return $hex[0] === '2' || $hex[0] === '3';
        }
        $n = ip2long($ip);
        foreach ([['100.64.0.0', 10], ['192.0.0.0', 24], ['192.0.2.0', 24], ['198.18.0.0', 15], ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4]] as [$base,$bits]) {
            $mask = -1 << (32 - $bits);
            if (($n & $mask) === (ip2long($base) & $mask)) {
                return false;
            }
        }

        return true;
    }
}
