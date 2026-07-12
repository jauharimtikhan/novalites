<?php

namespace Novalites\Support\Facades;

use Novalites\Support\Facade;
use Novalites\Database\Manager;

/**
 * @method static \Illuminate\Database\Query\Builder table(string $table)
 * @method static array select(string $query, array $bindings = [])
 * @method static bool insert(string $query, array $bindings = [])
 * @method static int update(string $query, array $bindings = [])
 * @method static int delete(string $query, array $bindings = [])
 * @method static mixed transaction(\Closure $callback)
 */
class DB extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
