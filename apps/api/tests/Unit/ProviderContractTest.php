<?php

namespace Tests\Unit;

use App\Domain\Connect\DemoProvider;
use App\Domain\Connect\GitHubProvider;
use App\Domain\Connect\RequestRejected;
use App\Domain\Connect\SlackProvider;
use PHPUnit\Framework\TestCase;

class ProviderContractTest extends TestCase
{
    public function test_distinct_provider_contracts_validate_realistic_operations(): void
    {
        $github = new GitHubProvider;
        $github->validate('GET', '/repos/acme/platform/issues', null);
        $github->validate('POST', '/repos/acme/platform/issues', ['title' => 'Synthetic issue']);
        $this->assertSame('2022-11-28', $github->headers()['X-GitHub-Api-Version']);
        $slack = new SlackProvider;
        $slack->validate('POST', '/chat.postMessage', ['channel' => '#engineering', 'text' => 'Synthetic notification']);
        $this->assertTrue($slack->accepted(['ok' => true]));
        $this->assertFalse($slack->accepted(['ok' => false, 'error' => 'invalid_auth']));
        $this->assertFalse($slack->accepted(null));
    }

    public function test_invalid_slack_payload_is_rejected_before_network(): void
    {
        $this->expectException(RequestRejected::class);
        (new SlackProvider)->validate('POST', '/chat.postMessage', ['channel' => '#engineering']);
    }

    public function test_demo_provider_has_a_bounded_operation_catalog(): void
    {
        $provider = new DemoProvider('demo-github');
        $provider->validate('GET', '/github/issues', null);
        $this->expectException(RequestRejected::class);
        $provider->validate('POST', '/register', ['secret' => 'no']);
    }
}
