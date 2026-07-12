<?php

namespace Novalites\Support\Facades;

use Novalites\Router\PendingGroup;
use Novalites\Router\PendingRoute;
use Novalites\Router\Route as RouterRoute;
use Novalites\Support\Facade;

/**
 * @method static PendingRoute get(string $path, mixed $action)
 * @method static PendingRoute post(string $path, mixed $action)
 * @method static PendingRoute put(string $path, mixed $action)
 * @method static PendingRoute patch(string $path, mixed $action)
 * @method static PendingRoute delete(string $path, mixed $action)
 * @method static PendingRoute any(string $path, mixed $action)
 * @method static PendingRoute options(string $path, mixed $action)
 * @method static PendingGroup prefix(string $prefix)
 * @method static PendingGroup middleware(array|string $middleware)
 * @method static PendingGroup namespace(string $namespace)
 * @method static PendingGroup domain(string $domain)
 * @method static PendingGroup name(string $name)
 * @method static array routes()
 */
class Route extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RouterRoute::class;
    }
}
