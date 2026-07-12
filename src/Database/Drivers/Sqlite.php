<?php

namespace Novalites\Database\Drivers;

use Illuminate\Database\Capsule\Manager as Capsule;

class Sqlite
{
    public static function boot(Capsule $capsule)
    {
        $capsule->addConnection([
            'driver' => config('database.sqlite.driver', 'sqlite') ?? 'sqlite',
            'database' => config('database.sqlite.database', '') ?? 'database',
            'prefix' => '',
        ]);
    }
}
