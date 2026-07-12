<?php

namespace Novalites\Router;

use Novalites\Container\Container;
use Novalites\Http\Request;

class Route
{
    protected static ?RouteCollection $collection = null;

    protected static ?RouteCompiler $compiler = null;

    /**
     * Route Collection.
     */
    protected static function collection(): RouteCollection
    {
        return self::$collection ??= new RouteCollection();
    }

    /**
     * Route Compiler.
     */
    protected static function compiler(): RouteCompiler
    {
        return self::$compiler ??= new RouteCompiler();
    }

    /**
     * Register route.
     */
    protected static function register(
        string $method,
        string $path,
        mixed $action
    ): PendingRoute {

        $route = self::compiler()->compile([
            'method' => strtoupper($method),

            'path' => $path,

            'action' => $action,

            'middleware' => [],

            'where' => [],

            'name' => null,

            'domain' => null,
        ]);

        $index = self::collection()->add($route);

        return new PendingRoute(
            self::collection(),
            $index
        );
    }

    /**
     * GET.
     */
    public static function get(
        string $path,
        mixed $action
    ): PendingRoute {

        return self::register(
            'GET',
            $path,
            $action
        );
    }

    /**
     * POST.
     */
    public static function post(
        string $path,
        mixed $action
    ): PendingRoute {

        return self::register(
            'POST',
            $path,
            $action
        );
    }

    /**
     * PUT.
     */
    public static function put(
        string $path,
        mixed $action
    ): PendingRoute {

        return self::register(
            'PUT',
            $path,
            $action
        );
    }

    /**
     * PATCH.
     */
    public static function patch(
        string $path,
        mixed $action
    ): PendingRoute {

        return self::register(
            'PATCH',
            $path,
            $action
        );
    }

    /**
     * DELETE.
     */
    public static function delete(
        string $path,
        mixed $action
    ): PendingRoute {

        return self::register(
            'DELETE',
            $path,
            $action
        );
    }

    /**
     * OPTIONS.
     */
    public static function options(
        string $path,
        mixed $action
    ): PendingRoute {

        return self::register(
            'OPTIONS',
            $path,
            $action
        );
    }

    /**
     * ANY.
     */
    public static function any(
        string $path,
        mixed $action
    ): PendingRoute {

        return self::register(
            'ANY',
            $path,
            $action
        );
    }

    /**
     * Route prefix.
     */
    public static function prefix(
        string $prefix
    ): PendingGroup {

        return (new PendingGroup(
            self::compiler()
        ))->prefix($prefix);
    }

    /**
     * Route middleware group.
     */
    public static function middleware(
        string|array $middleware
    ): PendingGroup {

        return (new PendingGroup(
            self::compiler()
        ))->middleware($middleware);
    }

    /**
     * Route namespace.
     */
    public static function namespace(
        string $namespace
    ): PendingGroup {

        return (new PendingGroup(
            self::compiler()
        ))->namespace($namespace);
    }

    /**
     * Route controller.
     */
    public static function controller(
        string $controller
    ): PendingGroup {

        return (new PendingGroup(
            self::compiler()
        ))->controller($controller);
    }

    /**
     * Route domain.
     */
    public static function domain(
        string $domain
    ): PendingGroup {

        return (new PendingGroup(
            self::compiler()
        ))->domain($domain);
    }

    /**
     * Route name prefix.
     */
    public static function name(
        string $name
    ): PendingGroup {

        return (new PendingGroup(
            self::compiler()
        ))->name($name);
    }

    /**
     * Dispatch router.
     */
    public static function dispatch(
        Request $request,
        Container $container
    ): mixed {

        return (new RouteResolver(
            self::collection()
        ))->resolve($request, $container);
    }

    public static function sendResponse(mixed $result): void
    {
        if ($result instanceof \Novalites\Http\Response) {
            $result->send();
            return;
        }

        if (is_string($result)) {
            \Novalites\Http\Response::html($result)->send();
            return;
        }

        \Novalites\Http\Response::json($result);
    }

    /**
     * Get registered routes.
     */
    public static function routes(): array
    {
        return self::collection()->all();
    }

    /**
     * Clear all routes.
     */
    public static function clear(): void
    {
        self::$collection = new RouteCollection();

        self::$compiler = new RouteCompiler();
    }
}
