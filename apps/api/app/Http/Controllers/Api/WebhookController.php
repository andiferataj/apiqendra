<?php

namespace App\Http\Controllers\Api;

use App\Domain\Relay\Ingress;
use App\Models\Endpoint;
use Illuminate\Http\Request;

class WebhookController
{
    public function __invoke(Request $r, string $endpoint): mixed
    {
        abort_unless(str_starts_with(strtolower((string) $r->header('Content-Type')), 'application/json'), 415, 'JSON content type required.');
        $e = Endpoint::findOrFail($endpoint);
        $headers = [];
        foreach (['x-hub-signature-256', 'x-github-delivery', 'x-github-event', 'x-apiqendra-signature', 'x-apiqendra-timestamp', 'x-event-id'] as $key) {
            $headers[$key] = (string) $r->header($key, '');
        }
        $result = app(Ingress::class)->accept($e, $r->getContent(), $headers, $r->attributes->get('request_id'));

        return response()->json(['event_id' => $result['event']->id, 'duplicate' => $result['duplicate']], $result['duplicate'] ? 200 : 202);
    }
}
