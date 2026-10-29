<?php

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

return ['default' => 'stderr', 'channels' => ['stderr' => ['driver' => 'monolog', 'handler' => StreamHandler::class, 'with' => ['stream' => 'php://stderr'], 'formatter' => JsonFormatter::class, 'level' => 'info'], 'null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]];
