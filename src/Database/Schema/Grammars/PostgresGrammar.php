<?php

namespace Novalites\Database\Schema\Grammars;

use Novalites\Database\Schema\Blueprint;
use Novalites\Database\Schema\ColumnDefinition;

class PostgresGrammar extends Grammar
{
    public function typeString(ColumnDefinition $col): string
    {
        // pgsql ga punya UNSIGNED & auto_increment beda (pakai SERIAL)
        if ($col->autoIncrement) {
            return $col->type === 'bigInteger' ? 'BIGSERIAL' : 'SERIAL';
        }

        return match ($col->type) {
            'string'       => "VARCHAR({$col->parameters[0]})",
            'text'         => 'TEXT',
            'longText'     => 'TEXT',
            'integer'      => 'INTEGER',
            'bigInteger'   => 'BIGINT',
            'smallInteger' => 'SMALLINT',
            'boolean'      => 'BOOLEAN',
            'decimal'      => "DECIMAL({$col->parameters[0]},{$col->parameters[1]})",
            'float'        => 'DOUBLE PRECISION',
            'date'         => 'DATE',
            'dateTime'     => 'TIMESTAMP',
            'timestamp'    => 'TIMESTAMP',
            'json'         => 'JSONB',
            'uuid'         => 'UUID',
            'enum'         => 'VARCHAR(255)', // pgsql enum butuh CREATE TYPE terpisah, disederhanakan ke varchar + check constraint kalau perlu
            default        => 'VARCHAR(255)',
        };
    }

    protected function supportsUnsigned(): bool
    {
        return false; // pgsql ga ada unsigned
    }

    protected function autoIncrementKeyword(): string
    {
        return ''; // udah dihandle via SERIAL di typeString
    }

    protected function compileColumn(ColumnDefinition $col): string
    {
        $sql = "{$col->name} " . $this->typeString($col);

        // kalau auto increment, SERIAL udah implicit NOT NULL + auto, skip auto keyword
        if (!$col->autoIncrement) {
            $sql .= $col->nullable ? ' NULL' : ' NOT NULL';

            if ($col->hasDefault) {
                $sql .= ' DEFAULT ' . $this->formatDefault($col->default);
            }
        }

        return $sql;
    }

    public function compileCreate(Blueprint $blueprint): string
    {
        $lines = $this->compileColumnDefinitions($blueprint);

        if ($blueprint->primaryColumn) {
            $lines[] = "PRIMARY KEY ({$blueprint->primaryColumn})";
        }

        $lines = array_merge($lines, $this->compileForeignKeys($blueprint));

        $body = implode(",\n  ", $lines);
        $sql = "CREATE TABLE {$blueprint->table} (\n  {$body}\n)";

        return $sql;
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
        $statements = [];

        foreach ($blueprint->columns as $col) {
            $def = $this->compileColumn($col);
            $statements[] = "ALTER TABLE {$blueprint->table} ADD COLUMN {$def}";
        }

        foreach ($blueprint->dropColumns as $col) {
            $statements[] = "ALTER TABLE {$blueprint->table} DROP COLUMN {$col}";
        }

        foreach ($blueprint->foreignKeys as $fk) {
            $statements[] = "ALTER TABLE {$blueprint->table} ADD FOREIGN KEY ({$fk->column}) "
                . "REFERENCES {$fk->referencesTable}({$fk->referencesColumn}) "
                . "ON DELETE {$fk->onDelete} ON UPDATE {$fk->onUpdate}";
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
        return []; // pgsql index dibuat via statement CREATE INDEX terpisah, bukan inline di CREATE TABLE
    }
}
