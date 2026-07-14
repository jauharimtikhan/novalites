<?php

namespace Novalites\Database\Relations;

use Novalites\Database\Model;
use RuntimeException;

class MorphTo extends Relation
{
    protected Model $parent;
    protected string $morphType;
    protected string $morphId;

    /** @var array<string,string> alias map, misal ['post' => Post::class] kalau type disimpan bukan FQCN */
    protected array $morphMap = [];

    public function __construct(Model $parent, string $morphName)
    {
        $this->parent = $parent;
        $this->morphType = $morphName . '_type';
        $this->morphId = $morphName . '_id';
    }

    public function morphMap(array $map): static
    {
        $this->morphMap = $map;
        return $this;
    }

    public function getResults(): ?Model
    {
        $type = $this->parent->getAttribute($this->morphType);
        $id   = $this->parent->getAttribute($this->morphId);

        if ($type === null || $id === null) {
            return null;
        }

        $class = $this->resolveClass($type);

        if (!class_exists($class)) {
            throw new RuntimeException("Morph target class [{$class}] does not exist.");
        }

        $result = $class::query()->find($id, (new $class())->getKeyName());
        return $result ?: null;
    }

    protected function resolveClass(string $type): string
    {
        // support morphMap alias (kayak Laravel: 'post' => Post::class)
        // kalau ga ada di map, anggap $type udah FQCN penuh
        return $this->morphMap[$type] ?? $type;
    }
}
