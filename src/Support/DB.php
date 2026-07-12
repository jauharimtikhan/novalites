<?php

namespace Novalites\Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use Novalites\Database\Manager;

class DB
{
    protected ?Capsule $capsule = null;

    public function __construct()
    {
        $this->capsule = Manager::getInstance();
    }

    public static function schema()
    {
        $t = new self;
        return $t->capsule->schema();
    }

    public static function table(string $name)
    {
        $t = (new self);
        return $t->capsule->table($name);
    }

    public static function beginTransaction()
    {
        $t = (new self);
        return $t->capsule->getDatabaseManager()->beginTransaction();
    }

    public static function commit()
    {
        $t = (new self);
        return $t->capsule->getDatabaseManager()->commit();
    }

    public static function rollBack()
    {
        $t = (new self);
        return $t->capsule->getDatabaseManager()->rollBack();
    }

    public static function dropAllTable()
    {
        $t = (new self);
        return $t->capsule->schema()->dropAllTables();
    }
}
