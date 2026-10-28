<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => \App\Http\Middleware\EnsureUserHasRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            if ($request->isMethod('post') && ($request->is('register') || $request->is('admin/users'))) {
                $isRegistration = $request->is('register');

                return response()->view('errors.upload-too-large', [
                    'returnUrl' => $isRegistration
                        ? route('register')
                        : route('workspace.page', 'accounts'),
                ], status: 413);
            }

            return null;
        });
    })->create();
