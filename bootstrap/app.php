<?php

use App\Http\Middleware\{CheckPermission,MustChangePassword};
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions,Middleware};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
        $middleware->alias([
            'permission'=>CheckPermission::class,
            'must.change.password'=>MustChangePassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
