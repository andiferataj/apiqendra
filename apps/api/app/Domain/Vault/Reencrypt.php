<?php

namespace App\Domain\Vault;

use App\Models\Credential;
use App\Models\CredentialVersion;
use App\Models\Destination;
use App\Models\Endpoint;
use App\Models\Event;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class Reencrypt
{
    public function run(): array
    {
        $updated = 0;
        $conflicts = 0;
        foreach ([Credential::class => 'value', CredentialVersion::class => 'value', Endpoint::class => 'secret', Destination::class => 'secret', Event::class => 'payload'] as $model => $field) {
            $model::orderBy('id')->chunk(100, function ($rows) use ($field, &$updated, &$conflicts) {
                foreach ($rows as $record) {
                    $old = $record->getRawOriginal($field);
                    $plain = $record->{$field};
                    $string = is_array($plain) ? json_encode($plain, JSON_THROW_ON_ERROR) : $plain;
                    $changed = DB::table($record->getTable())->where('id', $record->id)->where($field, $old)->update([$field => Crypt::encryptString($string)]);
                    if ($changed) {
                        $updated++;
                    } else {
                        $conflicts++;
                    }
                }
            });
        }

        return ['updated' => $updated, 'conflicts' => $conflicts];
    }
}
