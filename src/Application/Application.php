<?php

namespace Novalites\Application;

use Carbon\Carbon;
use Closure;
use Dotenv\Dotenv;
use Novalites\Auth\Auth;
use Novalites\Container\Container;
use Novalites\Database\Manager;
use Novalites\Exception\Handler;
use Novalites\Middleware\Middleware as MiddlewareKernel;
use Novalites\Http\Request;
use Novalites\Logging\Logger;
use Novalites\Middleware\Middlewares\ValidateCsrfToken;
use Novalites\Queue\QueueManager;
use Novalites\Router\Route;
use Novalites\Session\Session;
use Novalites\Support\Str;
use Novalites\Templating\TemplateEngine;


class Application
{
    protected array $webRoutes = [];
    protected array $apiRoutes = [];
    protected array $routeFiles = [];

    protected MiddlewareKernel $middlewareKernel;

    protected function __construct()
    {
        $this->middlewareKernel = new MiddlewareKernel();
    }

    public static function boot(string $basePath): static
    {
        define('BASE_PATH', $basePath);
        Handler::register();
        Logger::setPath($basePath . "/storage/logs");
        try {
            Dotenv::createImmutable($basePath)->load();
        } catch (\Dotenv\Exception\InvalidFileException $e) {
            // Tangkep khusus biar pesannya lebih jelas & actionable buat developer
            throw new \RuntimeException(
                'File .env gagal di-parse: ' . $e->getMessage() . '. Cek syntax .env kamu (value yang ada spasi wajib pakai tanda kutip).',
                0,
                $e
            );
        }
        Carbon::setLocale(jtech_env('APP_LOCALE'));

        return new static();
    }

    public function withRouting(?string $web = null, ?string $api = null): static
    {
        if ($web !== null && !in_array($web, $this->webRoutes, true)) {
            $this->webRoutes[] = $web;
        }

        if ($api !== null && !in_array($api, $this->apiRoutes, true)) {
            $this->apiRoutes[] = $api;

            Route::prefix('api')->group(function () use ($api) {
                if (file_exists($api)) {
                    require_once $api;
                }
            });
        }

        $this->routeFiles = [...$this->webRoutes];

        return $this;
    }

    /**
     * Konfigurasi middleware, mirip Laravel 11 bootstrap/app.php.
     *
     * Contoh pemakaian:
     *
     * $app->withMiddleware(function (MiddlewareKernel $middleware) {
     *     $middleware->append(TrimStrings::class);
     *
     *     $middleware->alias([
     *         'auth'     => AuthMiddleware::class,
     *         'throttle' => ThrottleMiddleware::class,
     *     ]);
     *
     *     $middleware->group('api', ['throttle']);
     * });
     */
    public function withMiddleware(Closure $callback): static
    {
        $callback($this->middlewareKernel);
        return $this;
    }

    public function run(): void
    {
        $container = Container::getInstance();
        $container->instance(MiddlewareKernel::class, $this->middlewareKernel);



        $request = new Request();
        $container->instance(Request::class, $request);

        $container->singleton(Manager::class);
        $container->singleton(QueueManager::class, fn() => QueueManager::getInstance());
        Manager::init();


        Session::driver();

        $this->middlewareKernel->append([
            ValidateCsrfToken::class
        ]);

        // Daftarin model User buat Auth
        Auth::useModel(\App\Models\User::class);
        Session::put('_csrf_token', Str::random(24));
        TemplateEngine::setViewsPath(BASE_PATH . '/resources/views');
        TemplateEngine::setCachePath(BASE_PATH . '/storage/framework/views');
        TemplateEngine::share('appName', 'Novalites Nova');

        register_shutdown_function(fn() => Session::commit());
        $this->loadRoutes();

        $result = Route::dispatch($request, $container);
        Route::sendResponse($result);
        Session::commit();
    }

    protected function loadRoutes(): void
    {
        $files = empty($this->routeFiles) ? ['web'] : $this->routeFiles;
        $basePath = \defined('BASE_PATH') ? constant('BASE_PATH') : dirname(__DIR__);

        require_once __DIR__ . '/../Support/default_route.php';

        foreach ($files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
}
