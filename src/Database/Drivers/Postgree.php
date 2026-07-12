<?php

namespace Novalites\Database\Drivers;

use Illuminate\Database\Capsule\Manager as Capsule;

class Postgree
{
    public static function boot(Capsule $capsule)
    {
        $capsule->addConnection([
            'driver' => config('database.postgree.driver', 'pgsql') ?? 'pgsql',
            'host' => config('database.postgree.host', 'localhost') ?? 'localhost',
            'database' => config('database.postgree.database', 'jtech_rest_api') ?? 'database',
            'username' => config('database.postgree.username', 'root') ?? 'root',
            'password' => config('database.postgree.password') ?? 'password',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => "public"
        ]);
    }
}
