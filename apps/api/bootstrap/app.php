<?php

use App\Domain\Connect\RequestRejected;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\RequestContext;
use App\Http\Middleware\WorkspaceAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up', then: function (): void {
        Route::post('/hooks/{endpoint}', WebhookController::class)->middleware('throttle:ingress')->whereUuid('endpoint');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['workspace' => WorkspaceAccess::class]);
        $middleware->preventRequestForgery();
        $middleware->append(RequestContext::class);
        $middleware->redirectGuestsTo(fn () => '/api/v1/session');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);
        $exceptions->respond(function (Response $response) {
            $response->headers->set('X-Request-ID', (string) request()->attributes->get('request_id', 'unavailable'));
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        });
        $exceptions->render(fn (RequestRejected $e, Request $request) => response()->json(['message' => $e->getMessage()], 422));
        $exceptions->render(fn (InvalidArgumentException $e, Request $request) => response()->json(['message' => $e->getMessage()], 422));
    })->create();
