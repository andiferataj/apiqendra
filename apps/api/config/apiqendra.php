<?php

return ['demo' => env('APP_ENV') === 'local' && (bool) env('DEMO_ENABLED', false), 'simulator_url' => 'http://simulator:8090', 'simulator_token' => env('DEMO_SIMULATOR_TOKEN'), 'max_payload_bytes' => 262144, 'max_response_bytes' => 131072, 'max_attempts' => 4, 'delivery_lease_seconds' => 60, 'ingress_per_minute' => 120, 'retention_days' => 30, 'metrics_token' => env('METRICS_TOKEN')];
