<?php

namespace Novalites\Router;

use Novalites\Container\Container;
use Novalites\Database\Model;
use Novalites\Http\Request;
use Novalites\Middleware\Middleware;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

class RouteResolver
{
    public function __construct(
        protected RouteCollection $routes
    ) {}

    /**
     * Resolve request.
     */
    public function resolve(Request $request, Container $container): mixed
    {
        // Bind instance Request yang aktif ke container,
        // biar service/middleware lain yang butuh Request di constructor-nya
        // otomatis dapat instance yang sama (bukan bikin baru).
        $container->instance(Request::class, $request);

        $kernel = $container->has(Middleware::class)
            ? $container->make(Middleware::class)
            : new Middleware();

        [$route, $params] = $this->findRoute($request);

        $this->runMiddlewareList($kernel->getGlobal(), $request, $container);

        // Baru middleware spesifik route (resolve alias/group dulu)
        $routeMiddlewares = $kernel->resolveMany($route['middleware'] ?? []);
        $this->runMiddlewareList($routeMiddlewares, $request, $container);

        return $this->invoke(
            $route,
            $request,
            $params,
            $container
        );
    }

    /**
     * Find matched route.
     */
    protected function findRoute(Request $request): array
    {
        $uri = parse_url($request->uri(), PHP_URL_PATH);

        $method = $request->method();

        $methodNotAllowed = false;

        foreach ($this->routes as $route) {

            $params = $this->matchRoute(
                $route['path'],
                $uri
            );

            if ($params === false) {
                continue;
            }

            if ($route['method'] !== $method) {
                $methodNotAllowed = true;
                continue;
            }

            return [$route, $params];
        }

        if ($methodNotAllowed) {
            abort(405);
        }

        abort(404);
    }

    /**
     * Execute middleware.
     */
    protected function runMiddlewareList(array $middlewares, Request $request, Container $container): void
    {
        foreach ($middlewares as $middleware) {
            $instance = $container->make($middleware);

            if (!method_exists($instance, 'handle')) {
                throw new RuntimeException("{$middleware} tidak memiliki method handle().");
            }

            $instance->handle($request);
        }
    }

    /**
     * Invoke route action.
     */
    protected function invoke(
        array $route,
        Request $request,
        array $params,
        Container $container
    ): mixed {

        $action = $route['action'];

        /*
        |--------------------------------------------------------------------------
        | Closure
        |--------------------------------------------------------------------------
        */

        if (is_callable($action) && !is_array($action)) {

            return call_user_func_array(
                $action,
                [
                    $request,
                    ...array_values($params)
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Controller
        |--------------------------------------------------------------------------
        */

        [$controllerClass, $method] = $action;

        // Instantiate controller lewat Container (auto-wire constructor dependency-nya)
        $controller = $container->make($controllerClass);

        $reflection = new ReflectionMethod(
            $controller,
            $method
        );

        $arguments = $this->resolveArguments(
            $reflection,
            $request,
            $params,
            $container
        );

        return $reflection->invokeArgs(
            $controller,
            $arguments
        );
    }

    /**
     * Resolve controller parameters.
     */
    protected function resolveArguments(
        ReflectionMethod $reflection,
        Request $request,
        array $params,
        Container $container
    ): array {

        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {

            $arguments[] = $this->resolveDependency(
                $parameter,
                $request,
                $params,
                $container
            );
        }

        return $arguments;
    }

    /**
     * Resolve dependency.
     */
    protected function resolveDependency(
        \ReflectionParameter $parameter,
        Request $request,
        array &$params,
        Container $container
    ): mixed {

        $type = $parameter->getType();

        if (
            $type instanceof ReflectionNamedType &&
            !$type->isBuiltin()
        ) {

            $class = $type->getName();

            /*
            |--------------------------------------------------------------------------
            | Request
            |--------------------------------------------------------------------------
            */

            if ($class === Request::class) {
                return $request;
            }

            /*
            |--------------------------------------------------------------------------
            | Model Binding (route parameter -> Eloquent/Model instance)
            |--------------------------------------------------------------------------
            */

            $reflection = new ReflectionClass($class);

            if ($reflection->isSubclassOf(Model::class)) {
                $value = array_shift($params);

                if ($value === null) {
                    abort(404);
                }

                $model = $class::find($value);

                if ($model === null) {
                    abort(404);
                }

                return $model;
            }

            /*
            |--------------------------------------------------------------------------
            | Dependency Injection (lewat Container, support auto-wiring)
            |--------------------------------------------------------------------------
            */

            return $container->make($class);
        }

        /*
        |--------------------------------------------------------------------------
        | Route Parameter (primitive: string/int dari URI)
        |--------------------------------------------------------------------------
        */

        if (!empty($params)) {
            return array_shift($params);
        }

        return $parameter->isDefaultValueAvailable()
            ? $parameter->getDefaultValue()
            : null;
    }

    /**
     * Match dynamic route.
     */
    protected function matchRoute(
        string $route,
        string $uri
    ): array|false {

        // 1. Ambil semua teks di dalam {}
        preg_match_all('/\{(.*?)\}/', $route, $matches);

        // 2. Bersihkan nama key dari tanda bintang (*) biar array asoc-nya rapi
        $keys = array_map(function ($key) {
            return rtrim($key, '*'); // 'slug*' berubah jadi 'slug'
        }, $matches[1]);

        // 3. Ubah {parameter} jadi Regex pakai callback
        $pattern = preg_replace_callback(
            '/\{(.*?)\}/',
            function ($m) {
                // Kalau nama parameter berakhiran bintang (contoh: {slug*})
                if (str_ends_with($m[1], '*')) {
                    return '(.*)'; // Tangkap semua karakter termasuk '/'
                }
                // Kalau parameter biasa (contoh: {id} atau {slug})
                return '([^\/]+)'; // Berhenti saat ketemu '/'
            },
            $route
        );

        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $uri, $values)) {
            return false;
        }

        array_shift($values);

        return array_combine(
            $keys,
            $values
        );
    }
}
