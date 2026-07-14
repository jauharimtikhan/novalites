<?php

namespace Novalites\Database\Relations;

use Novalites\Database\Model;

class MorphOne extends Relation
{
    protected Model $parent;
    protected string $related;
    protected string $morphType;   // kolom: commentable_type
    protected string $morphId;     // kolom: commentable_id
    protected string $localKey;

    public function __construct(Model $parent, string $related, string $morphName, string $localKey)
    {
        $this->parent = $parent;
        $this->related = $related;
        $this->morphType = $morphName . '_type';
        $this->morphId = $morphName . '_id';
        $this->localKey = $localKey;
    }

    public function getResults(): ?Model
    {
        $value = $this->parent->getAttribute($this->localKey);
        if ($value === null) {
            return null;
        }

        $result = $this->newRelatedQuery()
            ->where($this->morphId, $value)
            ->where($this->morphType, get_class($this->parent))
            ->first();

        return $result ?: null;
    }
}
