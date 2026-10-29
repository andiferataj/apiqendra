<?php

namespace Tests\Feature;

use App\Domain\Connect\RequestRejected;
use App\Domain\Connect\SafeHttp;
use App\Domain\Vault\CredentialAccess;
use App\Domain\Vault\Reencrypt;
use App\Models\Connection;
use App\Models\Credential;
use App\Models\CredentialVersion;
use App\Models\Destination;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CredentialPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function records(): array
    {
        $u = User::create(['name' => 'Credential Reviewer', 'email' => uniqid().'@example.local', 'password' => 'synthetic-password-123']);
        $w = Workspace::create(['name' => 'Credential test', 'slug' => uniqid(), 'environment' => 'sandbox']);
        Membership::create(['workspace_id' => $w->id, 'user_id' => $u->id, 'role' => 'developer']);
        $c = Credential::create(['workspace_id' => $w->id, 'name' => 'Restricted GitHub token', 'kind' => 'bearer', 'value' => 'synthetic-private-credential', 'allowed_hosts' => ['api.github.com']]);

        return [$u, $w, $c];
    }

    public function test_developer_cannot_exfiltrate_a_stored_credential_to_another_host(): void
    {
        [$u,$w,$c] = $this->records();
        $connector = Connection::create(['workspace_id' => $w->id, 'name' => 'Bad target', 'provider' => 'generic', 'base_url' => 'https://attacker.example', 'credential_id' => $c->id]);
        $http = $this->createMock(SafeHttp::class);
        $http->expects($this->never())->method('send');
        $this->app->instance(SafeHttp::class, $http);
        $this->actingAs($u)->postJson('/api/v1/connections/'.$connector->id.'/execute', ['method' => 'GET', 'path' => '/'], ['X-Workspace-ID' => $w->id])->assertUnprocessable()->assertJsonPath('message', 'Credential use is not allowed for this destination host.');
    }

    public function test_revoked_credentials_are_rejected_even_for_an_allowed_host(): void
    {
        [,,$c] = $this->records();
        $c->update(['status' => 'revoked']);
        $this->expectException(RequestRejected::class);
        CredentialAccess::header($c, 'https://api.github.com/user', null);
    }

    public function test_reencryption_preserves_plaintext_and_changes_ciphertext(): void
    {
        [,,$c] = $this->records();
        CredentialVersion::create(['workspace_id' => $c->workspace_id, 'credential_id' => $c->id, 'version' => 1, 'value' => $c->value]);
        $before = DB::table('credentials')->where('id', $c->id)->value('value');
        $result = app(Reencrypt::class)->run();
        $after = DB::table('credentials')->where('id', $c->id)->value('value');
        $this->assertSame(2, $result['updated']);
        $this->assertSame(0, $result['conflicts']);
        $this->assertNotSame($before, $after);
        $this->assertSame('synthetic-private-credential', $c->fresh()->value);
    }

    public function test_key_rotation_reencrypts_every_protected_record_without_old_key_dependency(): void
    {
        [, $workspace, $credential] = $this->records();
        CredentialVersion::create(['workspace_id' => $workspace->id, 'credential_id' => $credential->id, 'version' => 1, 'value' => $credential->value]);
        $endpoint = Endpoint::create(['workspace_id' => $workspace->id, 'name' => 'Rotation source', 'provider' => 'generic', 'secret' => 'synthetic-old-source-key', 'event_types' => ['issue.opened']]);
        $destination = Destination::create(['workspace_id' => $workspace->id, 'name' => 'Rotation sink', 'url' => 'https://example.com/events', 'secret' => 'synthetic-old-target-key']);
        $event = Event::create(['workspace_id' => $workspace->id, 'endpoint_id' => $endpoint->id, 'source_id' => 'rotation-event', 'event_type' => 'issue.opened', 'payload' => ['title' => 'Encrypted synthetic payload'], 'preview' => [], 'body_hash' => hash('sha256', 'rotation'), 'received_at' => now()]);
        $original = Crypt::getFacadeRoot();
        $newKey = random_bytes(32);
        $rotating = new Encrypter($newKey, 'AES-256-CBC');
        $rotating->previousKeys([$original->getKey()]);
        try {
            $this->app->instance('encrypter', $rotating);
            Crypt::clearResolvedInstance('encrypter');
            Model::encryptUsing($rotating);
            $this->assertSame(5, app(Reencrypt::class)->run()['updated']);
            $currentOnly = new Encrypter($newKey, 'AES-256-CBC');
            $this->app->instance('encrypter', $currentOnly);
            Crypt::clearResolvedInstance('encrypter');
            Model::encryptUsing($currentOnly);
            $this->assertSame('synthetic-private-credential', $credential->fresh()->value);
            $this->assertSame('synthetic-private-credential', CredentialVersion::first()->value);
            $this->assertSame('synthetic-old-source-key', $endpoint->fresh()->secret);
            $this->assertSame('synthetic-old-target-key', $destination->fresh()->secret);
            $this->assertSame(['title' => 'Encrypted synthetic payload'], $event->fresh()->payload);
        } finally {
            $this->app->instance('encrypter', $original);
            Crypt::clearResolvedInstance('encrypter');
            Model::encryptUsing(null);
        }
    }
}
