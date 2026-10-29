<?php

namespace App\Domain\Connect;

use App\Domain\Connect\RequestRejected as RuntimeException;

class SafeHttp
{
    public function __construct(private NetworkPolicy $policy) {}

    public function send(string $url, string $method = 'POST', ?string $body = null, array $headers = []): array
    {
        // Resolve immediately before connection and pin that vetted address in curl.
        $target = $this->policy->resolve($url);
        if (! in_array($method, ['GET', 'POST'], true)) {
            throw new RuntimeException('Unsupported method.');
        }
        if (strlen($body ?? '') > config('apiqendra.max_payload_bytes')) {
            throw new RuntimeException('Outbound payload exceeds the limit.');
        }
        $buffer = '';
        $receivedHeaders = [];
        $exceeded = false;
        $started = hrtime(true);
        $curl = curl_init($url);
        $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];
        $headers += ['User-Agent' => 'Apiqendra/0.1', 'Accept' => 'application/json'];
        $lines = [];
        foreach ($headers as $key => $value) {
            if (preg_match('/[\r\n]/', $key.$value)) {
                throw new RuntimeException('Invalid outbound header.');
            }
            $lines[] = $key.': '.$value;
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $lines,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => $target['demo'] ? CURLPROTO_HTTP : CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => 0, CURLOPT_PROXY => '',
            CURLOPT_CONNECTTIMEOUT_MS => 2000, CURLOPT_TIMEOUT_MS => 6000,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.$ip],
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$buffer, &$exceeded): int {
                if (strlen($buffer) + strlen($chunk) > config('apiqendra.max_response_bytes')) {
                    $exceeded = true;

                    return 0;
                }
                $buffer .= $chunk;

                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => function ($handle, string $line) use (&$receivedHeaders): int {
                if (str_contains($line, ':')) {
                    [$k,$v] = explode(':', $line, 2);
                    if (strtolower(trim($k)) === 'retry-after') {
                        $receivedHeaders['retry-after'] = mb_substr(trim($v), 0, 100);
                    }
                }

                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        curl_exec($curl);
        $errno = curl_errno($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return ['status' => $status ?: null, 'latency_ms' => (int) ((hrtime(true) - $started) / 1e6), 'body' => $buffer, 'headers' => $receivedHeaders, 'error' => $exceeded ? 'response_limit' : ($errno ? ($errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'transport_error') : null), 'uncertain' => $errno !== 0];
    }
}
