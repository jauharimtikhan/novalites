<?php

namespace Novalites\Database\Relations;

use Novalites\Database\Model;
use Novalites\Database\Manager;

class BelongsToMany extends Relation
{
    protected string $pivotTable;
    protected string $foreignPivotKey;
    protected string $relatedPivotKey;

    public function __construct(
        Model $parent,
        string $related,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey
    ) {
        $this->parent = $parent;
        $this->related = $related;
        $this->pivotTable = $pivotTable;
        $this->foreignPivotKey = $foreignPivotKey;
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
                WHERE {$this->pivotTable}.{$this->foreignPivotKey} = ?";

        $stmt = Manager::getConnection()->prepare($sql);
        $stmt->execute([$parentId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->related::newFromBuilder($row), $rows);
    }

    public function attach(int|string $relatedId, array $pivotData = []): void
    {
        $data = array_merge([
            $this->foreignPivotKey => $this->parent->getKey(),
            $this->relatedPivotKey => $relatedId,
        ], $pivotData);

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->pivotTable} ({$columns}) VALUES ({$placeholders})";
        Manager::getConnection()->prepare($sql)->execute(array_values($data));
    }

    public function detach(int|string|null $relatedId = null): void
    {
        $sql = "DELETE FROM {$this->pivotTable} WHERE {$this->foreignPivotKey} = ?";
        $bindings = [$this->parent->getKey()];

        if ($relatedId !== null) {
            $sql .= " AND {$this->relatedPivotKey} = ?";
            $bindings[] = $relatedId;
        }

        Manager::getConnection()->prepare($sql)->execute($bindings);
    }
}
