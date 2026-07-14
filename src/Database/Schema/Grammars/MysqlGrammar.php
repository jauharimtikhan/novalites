<?php

namespace Novalites\Database\Schema\Grammars;

use Novalites\Database\Schema\Blueprint;
use Novalites\Database\Schema\ColumnDefinition;

class MySqlGrammar extends Grammar
{
    public function typeString(ColumnDefinition $col): string
    {
        return match ($col->type) {
            'string'       => "VARCHAR({$col->parameters[0]})",
            'text'         => 'TEXT',
            'longText'     => 'LONGTEXT',
            'integer'      => 'INT',
            'bigInteger'   => 'BIGINT',
            'smallInteger' => 'SMALLINT',
            'boolean'      => 'TINYINT(1)',
            'decimal'      => "DECIMAL({$col->parameters[0]},{$col->parameters[1]})",
            'float'        => "FLOAT({$col->parameters[0]},{$col->parameters[1]})",
            'date'         => 'DATE',
            'dateTime'     => 'DATETIME',
            'timestamp'    => 'TIMESTAMP',
            'json'         => 'JSON',
            'uuid'         => 'CHAR(36)',
            'enum'         => "ENUM('" . implode("','", $col->parameters) . "')",
            default        => 'VARCHAR(255)',
        };
    }

    protected function supportsUnsigned(): bool
    {
        return true;
    }

    protected function autoIncrementKeyword(): string
    {
        return 'AUTO_INCREMENT';
    }

    public function compileCreate(Blueprint $blueprint): string
    {
        $lines = $this->compileColumnDefinitions($blueprint);

        if ($blueprint->primaryColumn) {
            $lines[] = "PRIMARY KEY ({$blueprint->primaryColumn})";
        }

        $lines = array_merge($lines, $this->compileIndexes($blueprint), $this->compileForeignKeys($blueprint));

        $body = implode(",\n  ", $lines);

        $sql = "CREATE TABLE {$blueprint->table} (\n  {$body}\n)";
        $sql .= " ENGINE={$blueprint->engine} DEFAULT CHARSET={$blueprint->charset} COLLATE={$blueprint->collation}";

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
            $sql = "ALTER TABLE {$blueprint->table} ADD COLUMN {$def}";
            if ($col->after) {
                $sql .= " AFTER {$col->after}";
            }
            $statements[] = $sql;
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
            $statements[] = "ALTER TABLE {$blueprint->table} ADD {$keyword} {$idx['name']} ({$cols})";
        }

        return $statements;
    }
}
