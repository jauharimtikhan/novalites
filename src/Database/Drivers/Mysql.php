<?php

namespace Novalites\Database\Drivers;

use Illuminate\Database\Capsule\Manager as Capsule;

class Mysql
{
    public static function boot(Capsule $capsule)
    {
        $capsule->addConnection([
            'driver' => config('database.mysql.driver', 'mysql') ?? 'mysql',
            'host' => config('database.mysql.host', 'localhost') ?? 'localhost',
            'database' => config('database.mysql.database', 'jtech_rest_api') ?? 'database',
            'username' => config('database.mysql.username', 'root') ?? 'root',
            'password' => config('database.mysql.password', '') ?? 'password',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ]);
    }
}
