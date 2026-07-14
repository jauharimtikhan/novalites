<?php

namespace Novalites\Database\Schema\Grammars;

use Novalites\Database\Schema\Blueprint;
use Novalites\Database\Schema\ColumnDefinition;

class SQLiteGrammar extends Grammar
{
    public function typeString(ColumnDefinition $col): string
    {
        return match ($col->type) {
            'string', 'text', 'longText', 'uuid', 'enum' => 'TEXT',
            'integer', 'bigInteger', 'smallInteger', 'boolean' => 'INTEGER',
            'decimal', 'float' => 'REAL',
            'date', 'dateTime', 'timestamp' => 'TEXT',
            'json' => 'TEXT',
            default => 'TEXT',
        };
    }

    protected function supportsUnsigned(): bool
    {
        return false;
    }

    protected function autoIncrementKeyword(): string
    {
        return 'AUTOINCREMENT';
    }

    protected function compileColumn(ColumnDefinition $col): string
    {
        $sql = "{$col->name} " . $this->typeString($col);

        if ($col->autoIncrement) {
            // sqlite: INTEGER PRIMARY KEY AUTOINCREMENT wajib jadi primary key tunggal
            return "{$col->name} INTEGER PRIMARY KEY AUTOINCREMENT";
        }

        $sql .= $col->nullable ? ' NULL' : ' NOT NULL';

        if ($col->hasDefault) {
            $sql .= ' DEFAULT ' . $this->formatDefault($col->default);
        }

        return $sql;
    }

    public function compileCreate(Blueprint $blueprint): string
    {
        $lines = $this->compileColumnDefinitions($blueprint);

        // kalau ga ada auto increment column, tambahin PRIMARY KEY manual
        $hasAutoIncrement = array_filter($blueprint->columns, fn($c) => $c->autoIncrement);
        if (empty($hasAutoIncrement) && $blueprint->primaryColumn) {
            $lines[] = "PRIMARY KEY ({$blueprint->primaryColumn})";
        }

        $lines = array_merge($lines, $this->compileForeignKeys($blueprint));

        $body = implode(",\n  ", $lines);
        return "CREATE TABLE {$blueprint->table} (\n  {$body}\n)";
    }

    public function compileDrop(string $table): string
    {
        return "DROP TABLE {$table}";
    }

    public function compileDropIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS {$table}";
    }

    public function compileAlter(Blueprint $blueprint): array
    {
        // SQLite cuma support ADD COLUMN via ALTER TABLE, ga support DROP COLUMN / ADD FOREIGN KEY langsung
        $statements = [];

        foreach ($blueprint->columns as $col) {
            $def = $this->compileColumn($col);
            $statements[] = "ALTER TABLE {$blueprint->table} ADD COLUMN {$def}";
        }

        if (!empty($blueprint->dropColumns)) {
            // SQLite modern (3.35+) udah support DROP COLUMN
            foreach ($blueprint->dropColumns as $col) {
                $statements[] = "ALTER TABLE {$blueprint->table} DROP COLUMN {$col}";
            }
        }

        foreach ($blueprint->getIndexes() as $idx) {
            $cols = implode(', ', $idx['columns']);
            $keyword = $idx['type'] === 'unique' ? 'UNIQUE INDEX' : 'INDEX';
            $statements[] = "CREATE {$keyword} {$idx['name']} ON {$blueprint->table} ({$cols})";
        }

        return $statements;
    }

    protected function compileIndexes(Blueprint $blueprint): array
    {
        return [];
    }
}
