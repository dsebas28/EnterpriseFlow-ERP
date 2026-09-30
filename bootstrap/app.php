<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentCompany;
use App\Support\Api\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [ForceJsonResponse::class]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'tenant' => ResolveCurrentCompany::class,
        ]);

        // Resolve the tenant before route-model binding, so bound models
        // are already scoped to the active company (foreign ids => 404).
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveCurrentCompany::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // One error envelope for every API failure (see ApiExceptionRenderer).
        $exceptions->render(new ApiExceptionRenderer);
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
