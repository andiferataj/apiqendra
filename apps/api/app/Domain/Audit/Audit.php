<?php

namespace App\Domain\Audit;

use App\Domain\Vault\Redactor;
use App\Models\AuditLog;

class Audit
{
    public static function write(string $workspace, ?string $actor, string $action, ?string $resource = null, array $metadata = []): void
    {
        AuditLog::create(['workspace_id' => $workspace, 'actor_id' => $actor, 'action' => $action, 'resource_id' => $resource, 'metadata' => Redactor::clean($metadata)]);
    }
}
