<?php

namespace Novalites\Database\Relations;

use Novalites\Database\Model;
use Novalites\Database\Manager;

class MorphToMany extends Relation
{
    protected Model $parent;
    protected string $related;
    protected string $pivotTable;   // misal 'taggables'
    protected string $morphType;    // misal 'taggable_type'
    protected string $morphId;      // misal 'taggable_id'
    protected string $relatedPivotKey; // misal 'tag_id'

    public function __construct(
        Model $parent,
        string $related,
        string $pivotTable,
        string $morphName,
        string $relatedPivotKey
    ) {
        $this->parent = $parent;
        $this->related = $related;
        $this->pivotTable = $pivotTable;
        $this->morphType = $morphName . '_type';
        $this->morphId = $morphName . '_id';
        $this->relatedPivotKey = $relatedPivotKey;
    }

    public function getResults(): array
    {
        $parentId = $this->parent->getKey();
        if ($parentId === null) {
            return [];
        }

        $relatedInstance = new $this->related();
        $relatedTable = $relatedInstance->getTable();
        $relatedKey = $relatedInstance->getKeyName();

        $sql = "SELECT {$relatedTable}.* FROM {$relatedTable}
                INNER JOIN {$this->pivotTable}
                ON {$this->pivotTable}.{$this->relatedPivotKey} = {$relatedTable}.{$relatedKey}
                WHERE {$this->pivotTable}.{$this->morphId} = ?
                AND {$this->pivotTable}.{$this->morphType} = ?";

        $stmt = Manager::getConnection()->prepare($sql);
        $stmt->execute([$parentId, get_class($this->parent)]);

        return array_map(fn($row) => $this->related::newFromBuilder($row), $stmt->fetchAll());
    }

    public function attach(int|string $relatedId, array $pivotData = []): void
    {
        $data = array_merge([
            $this->morphId         => $this->parent->getKey(),
            $this->morphType       => get_class($this->parent),
            $this->relatedPivotKey => $relatedId,
        ], $pivotData);

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->pivotTable} ({$columns}) VALUES ({$placeholders})";
        Manager::getConnection()->prepare($sql)->execute(array_values($data));
    }

    public function detach(int|string|null $relatedId = null): void
    {
        $sql = "DELETE FROM {$this->pivotTable} WHERE {$this->morphId} = ? AND {$this->morphType} = ?";
        $bindings = [$this->parent->getKey(), get_class($this->parent)];

        if ($relatedId !== null) {
            $sql .= " AND {$this->relatedPivotKey} = ?";
            $bindings[] = $relatedId;
        }

        Manager::getConnection()->prepare($sql)->execute($bindings);
    }
}
