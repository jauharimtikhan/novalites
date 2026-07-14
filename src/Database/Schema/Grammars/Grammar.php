<?php

namespace Novalites\Database\Schema\Grammars;

use Novalites\Database\Schema\Blueprint;
use Novalites\Database\Schema\ColumnDefinition;

abstract class Grammar
{
    abstract public function typeString(ColumnDefinition $col): string;
    abstract public function compileCreate(Blueprint $blueprint): string;
    abstract public function compileDrop(string $table): string;
    abstract public function compileDropIfExists(string $table): string;
    abstract public function compileAlter(Blueprint $blueprint): array;

    protected function compileColumnDefinitions(Blueprint $blueprint): array
    {
        $lines = [];
        foreach ($blueprint->columns as $col) {
            $lines[] = $this->compileColumn($col);
        }
        return $lines;
    }

    protected function compileColumn(ColumnDefinition $col): string
    {
        $sql = "{$col->name} " . $this->typeString($col);

        if ($col->unsigned && $this->supportsUnsigned()) {
            $sql .= ' UNSIGNED';
        }

        $sql .= $col->nullable ? ' NULL' : ' NOT NULL';

        if ($col->hasDefault) {
            $sql .= ' DEFAULT ' . $this->formatDefault($col->default);
        }

        if ($col->autoIncrement) {
            $sql .= ' ' . $this->autoIncrementKeyword();
        }

        return $sql;
    }

    protected function formatDefault(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if ($value === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }
        return "'" . addslashes($value) . "'";
    }

    protected function compileForeignKeys(Blueprint $blueprint): array
    {
        $lines = [];
        foreach ($blueprint->foreignKeys as $fk) {
            $lines[] = "FOREIGN KEY ({$fk->column}) REFERENCES {$fk->referencesTable}({$fk->referencesColumn}) "
                . "ON DELETE {$fk->onDelete} ON UPDATE {$fk->onUpdate}";
        }
        return $lines;
    }

    protected function compileIndexes(Blueprint $blueprint): array
    {
        $lines = [];
        foreach ($blueprint->getIndexes() as $idx) {
            $cols = implode(', ', $idx['columns']);
            $keyword = $idx['type'] === 'unique' ? 'UNIQUE KEY' : 'KEY';
            $lines[] = "{$keyword} {$idx['name']} ({$cols})";
        }
        return $lines;
    }

    abstract protected function supportsUnsigned(): bool;
    abstract protected function autoIncrementKeyword(): string;
}
