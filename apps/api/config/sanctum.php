<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return ['stateful' => [Sanctum::currentRequestHost()], 'guard' => ['web'], 'expiration' => null, 'token_prefix' => '', 'middleware' => ['authenticate_session' => AuthenticateSession::class, 'encrypt_cookies' => EncryptCookies::class, 'validate_csrf_token' => PreventRequestForgery::class]];
