<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        // Atrás do proxy reverso (Caddy) em produção: confia nos cabeçalhos X-Forwarded-*
        // para o Laravel reconhecer o HTTPS (URLs de assets e links assinados de e-mail).
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [\App\Http\Middleware\HandleInertiaRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('finance:sync-sheets')->everyMinute()->withoutOverlapping();
    })
    ->create();
