<?php

namespace Novalites\Support;

use Novalites\Database\Manager;
use Novalites\Database\Schema\Schema;

class DB
{
    protected ?Manager $capsule = null;

    public function __construct()
    {
        $this->capsule = new Manager();
    }

    public static function schema()
    {
        return new Schema;
    }

    public static function table(string $name)
    {
        $t = (new self);
        return $t->capsule->table($name);
    }

    public static function beginTransaction()
    {
        $t = (new self);
        return $t->capsule->beginTransaction();
    }

    public static function commit()
    {
        $t = (new self);
        return $t->capsule->commit();
    }

    public static function rollBack()
    {
        $t = (new self);
        return $t->capsule->rollBack();
    }

    public static function dropAllTable()
    {
        return Schema::dropAllTables(Manager::getDefaultConnectionName());
    }
}
