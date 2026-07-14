<?php

namespace Novalites\Database\Schema;

class ForeignKeyDefinition
{
    public string $column;
    public string $referencesTable;
    public string $referencesColumn = 'id';
    public string $onDelete = 'RESTRICT';
    public string $onUpdate = 'RESTRICT';

    public function __construct(string $column)
    {
        $this->column = $column;
    }

    public function references(string $column): static
    {
        $this->referencesColumn = $column;
        return $this;
    }

    public function on(string $table): static
    {
        $this->referencesTable = $table;
        return $this;
    }

    public function onDelete(string $action): static
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    public function cascadeOnDelete(): static
    {
        return $this->onDelete('CASCADE');
    }

    public function nullOnDelete(): static
    {
        return $this->onDelete('SET NULL');
    }
}
