<?php

namespace Novalites\Database\Relations;

use Novalites\Database\Model;

class HasOne extends Relation
{
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(Model $parent, string $related, string $foreignKey, string $localKey)
    {
        $this->parent = $parent;
        $this->related = $related;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    public function getResults(): ?Model
    {
        $value = $this->parent->getAttribute($this->localKey);
        if ($value === null) {
            return null;
        }

        $result = $this->newRelatedQuery()->where($this->foreignKey, $value)->first();
        return $result ?: null;
    }
}
