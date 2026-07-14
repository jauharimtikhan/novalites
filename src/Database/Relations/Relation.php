<?php

namespace Novalites\Database\Relations;

use Novalites\Database\Model;

abstract class Relation
{
    protected Model $parent;
    protected string $related;

    abstract public function getResults(): mixed;

    protected function newRelatedQuery()
    {
        return $this->related::query();
    }
}
