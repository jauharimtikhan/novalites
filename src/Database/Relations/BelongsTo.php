<?php

namespace Novalites\Database\Relations;

use Novalites\Database\Model;

class BelongsTo extends Relation
{
    protected string $foreignKey;
    protected string $ownerKey;

    public function __construct(Model $parent, string $related, string $foreignKey, string $ownerKey)
    {
        $this->parent = $parent;
        $this->related = $related;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    public function getResults(): ?Model
    {
        $value = $this->parent->getAttribute($this->foreignKey);
        if ($value === null) {
            return null;
        }

        $result = $this->newRelatedQuery()->where($this->ownerKey, $value)->first();
        return $result ?: null;
    }
}
