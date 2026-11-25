<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([\App\Console\Commands\SystemBackupCommand::class, \App\Console\Commands\RecycleBinPruneCommand::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\RecordActivity::class]);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
        $middleware->redirectUsersTo(function () {
            return auth()->user()?->dashboardRoute() ?? '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
