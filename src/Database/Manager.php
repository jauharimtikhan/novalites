<?php

namespace Novalites\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Novalites\Database\Drivers\Mysql;
use Novalites\Database\Drivers\Postgree;
use Novalites\Database\Drivers\Sqlite;

class Manager
{
    protected static ?Capsule $capsule = null;

    public static function init()
    {
        $driver = jtech_env("DB_DRIVER", 'mysql');
        if (self::$capsule === null) {

            $capsule = new Capsule();
            match ($driver) {
                'mysql' => Mysql::boot($capsule),
                'sqlite' => Sqlite::boot($capsule),
                'postgree' => Postgree::boot($capsule),
                default => Mysql::boot($capsule),
            };


            $capsule->setEventDispatcher(new Dispatcher());
            $capsule->setAsGlobal();

            $capsule->bootEloquent();

            self::$capsule = $capsule;
        }
        return self::$capsule;
    }

    public static function getInstance()
    {
        if (self::$capsule === null) {
            self::init();
        }
        return self::$capsule;
    }
}
