<?php

namespace Tests\Unit;

use App\Domain\Connect\NetworkPolicy;
use App\Domain\Relay\Mapper;
use App\Domain\Relay\RetryPolicy;
use App\Domain\Relay\Signature;
use App\Domain\Vault\Redactor;
use PHPUnit\Framework\TestCase;

class RelayPrimitivesTest extends TestCase
{
    public function test_signature_uses_raw_bytes_and_rejects_stale_timestamp(): void
    {
        $this->assertSame('v1=a86c12f10bed5d09cf42d3455218be90f5d859dacbbbc946aa2c29f79631670b', Signature::sign('{"hello":"world"}', 'synthetic-test-key', 1700000000));
        $body = '{"type":"issue.opened","data":{"a":1}}';
        $secret = 'synthetic-signing-secret';
        $signature = Signature::sign($body, $secret, 1000);
        $this->assertTrue(Signature::verify($body, $secret, $signature, '1000', 1100));
        $this->assertFalse(Signature::verify($body.' ', $secret, $signature, '1000', 1100));
        $this->assertFalse(Signature::verify($body, $secret, $signature, '1000', 1400));
        $this->assertFalse(Signature::verify($body, $secret, $signature, '-1000', 1100));
        $this->assertTrue(Signature::github($body, $secret, 'sha256='.hash_hmac('sha256', $body, $secret)));
        $this->assertFalse(Signature::github($body, $secret, $signature));
    }

    public function test_mapping_has_safe_explicit_transforms_and_failure_semantics(): void
    {
        $this->assertSame(['text' => 'HELLO', 'channel' => '#dev'], Mapper::apply(['issue' => ['title' => 'Hello']], ['text' => ['path' => 'issue.title', 'transform' => 'upper'], 'channel' => ['literal' => '#dev']]));
        $this->expectException(\InvalidArgumentException::class);
        Mapper::apply([], ['text' => ['path' => 'missing']]);
    }

    public function test_arbitrary_code_mapping_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Mapper::validate(['text' => ['transform' => 'eval', 'path' => 'title']]);
    }

    public function test_private_reserved_metadata_and_mapped_addresses_are_blocked(): void
    {
        foreach (['127.0.0.1', '10.0.0.1', '172.16.1.1', '192.168.1.1', '169.254.169.254', '100.64.0.1', '192.0.2.1', '198.18.0.1', '224.0.0.1', '::1', 'fc00::1', 'fe80::1', '::ffff:127.0.0.1', '2002:7f00:1::', '2001::1', '2001:0:1234::', '2001:2::1', '2001:db8::1', '3fff::1'] as $ip) {
            $this->assertFalse(NetworkPolicy::publicIp($ip), $ip);
        }
        $this->assertTrue(NetworkPolicy::publicIp('8.8.8.8'));
        $this->assertTrue(NetworkPolicy::publicIp('2606:4700:4700::1111'));
        $this->assertTrue(NetworkPolicy::publicIp('2001:4860:4860::8888'));
    }

    public function test_retry_policy_is_bounded_and_distinguishes_uncertainty(): void
    {
        $this->assertSame('delivered', RetryPolicy::classify(204, null));
        $this->assertSame('rate_limited', RetryPolicy::classify(429, null));
        $this->assertSame('retryable', RetryPolicy::classify(503, null));
        $this->assertSame('terminal', RetryPolicy::classify(302, null));
        $this->assertSame('terminal', RetryPolicy::classify(401, null));
        $this->assertSame('uncertain', RetryPolicy::classify(null, 'timeout'));
        $this->assertGreaterThanOrEqual(30, RetryPolicy::delay(1, '30'));
        $this->assertLessThanOrEqual(120, RetryPolicy::delay(100, '999999'));
    }

    public function test_redaction_is_recursive(): void
    {
        $out = Redactor::clean(['title' => 'Synthetic issue', 'nested' => ['Authorization' => 'Bearer token', 'email' => 'test@example.local', 'password' => 'private']]);
        $this->assertSame('Synthetic issue', $out['title']);
        foreach ($out['nested'] as $value) {
            $this->assertSame('[redacted]', $value);
        }
    }
}
