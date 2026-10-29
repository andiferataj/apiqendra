<?php

return ['default' => env('CACHE_STORE', 'redis'), 'stores' => ['redis' => ['driver' => 'redis', 'connection' => 'cache'], 'array' => ['driver' => 'array', 'serialize' => false], 'database' => ['driver' => 'database', 'table' => 'cache', 'lock_table' => 'cache_locks']], 'prefix' => 'apiqendra_cache'];
