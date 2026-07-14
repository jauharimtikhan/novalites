<?php

namespace Novalites\Database\Schema;

class Blueprint
{
    public string $table;
    public string $action; // 'create' | 'alter'

    /** @var ColumnDefinition[] */
    public array $columns = [];

    /** @var ForeignKeyDefinition[] */
    public array $foreignKeys = [];

    /** @var array kolom yang mau di-drop (buat alter table) */
    public array $dropColumns = [];

    public ?string $engine = 'InnoDB'; // relevan buat mysql doang
    public ?string $charset = 'utf8mb4';
    public ?string $collation = 'utf8mb4_unicode_ci';

    public function __construct(string $table, string $action = 'create')
    {
        $this->table = $table;
        $this->action = $action;
    }

    // ── ID / TIMESTAMP SHORTCUTS ──────────────────────────

    public function id(string $name = 'id'): ColumnDefinition
    {
        $col = $this->addColumn($name, 'bigInteger');
        $col->unsigned()->autoIncrement();
        $this->primaryColumn = $name;
        return $col;
    }

    public string $primaryColumn = 'id';

    public function timestamps(): void
    {
        $this->addColumn('created_at', 'timestamp')->nullable();
        $this->addColumn('updated_at', 'timestamp')->nullable();
    }

    public function softDeletes(string $name = 'deleted_at'): ColumnDefinition
    {
        return $this->addColumn($name, 'timestamp')->nullable();
    }

    public function rememberToken(): ColumnDefinition
    {
        return $this->addColumn('remember_token', 'string', [100])->nullable();
    }

    // ── COLUMN TYPES ───────────────────────────────────────

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($name, 'string', [$length]);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'text');
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'longText');
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'integer');
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'bigInteger');
    }

    public function unsignedBigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'bigInteger')->unsigned();
    }

    public function smallInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'smallInteger');
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'boolean');
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn($name, 'decimal', [$precision, $scale]);
    }

    public function float(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn($name, 'float', [$precision, $scale]);
    }

    public function date(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'date');
    }

    public function dateTime(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'dateTime');
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'timestamp');
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'json');
    }

    public function uuid(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'uuid', [36]);
    }

    public function enum(string $name, array $values): ColumnDefinition
    {
        return $this->addColumn($name, 'enum', $values);
    }

    // ── RELATION SHORTCUTS ─────────────────────────────────

    public function foreignId(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'bigInteger')->unsigned();
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $fk = new ForeignKeyDefinition($column);
        $this->foreignKeys[] = $fk;
        return $fk;
    }

    /**
     * Shortcut kayak Laravel 8+: $table->foreignIdFor(User::class)
     * bikin kolom user_id + langsung siap di-constraint
     */
    public function foreignIdFor(string $modelClass, ?string $columnName = null): ColumnDefinition
    {
        $instance = new $modelClass();
        $columnName ??= $instance->getForeignKeyName();
        return $this->foreignId($columnName);
    }

    /**
     * Kolom pasangan buat polymorphic, misal morphs('commentable')
     * bikin commentable_type (string) + commentable_id (bigInteger unsigned)
     */
    public function morphs(string $name): void
    {
        $this->addColumn($name . '_type', 'string', [255]);
        $this->addColumn($name . '_id', 'bigInteger')->unsigned();
        $this->index([$name . '_type', $name . '_id']);
    }

    public function nullableMorphs(string $name): void
    {
        $this->addColumn($name . '_type', 'string', [255])->nullable();
        $this->addColumn($name . '_id', 'bigInteger')->unsigned()->nullable();
        $this->index([$name . '_type', $name . '_id']);
    }

    // ── INDEX ──────────────────────────────────────────────

    protected array $indexes = [];

    public function index(array|string $columns, ?string $name = null): void
    {
        $columns = (array) $columns;
        $name ??= $this->table . '_' . implode('_', $columns) . '_index';
        $this->indexes[] = ['type' => 'index', 'columns' => $columns, 'name' => $name];
    }

    public function unique(array|string $columns, ?string $name = null): void
    {
        $columns = (array) $columns;
        $name ??= $this->table . '_' . implode('_', $columns) . '_unique';
        $this->indexes[] = ['type' => 'unique', 'columns' => $columns, 'name' => $name];
    }

    public function getIndexes(): array
    {
        return $this->indexes;
    }

    // ── DROP (alter table) ─────────────────────────────────

    public function dropColumn(string|array $columns): void
    {
        $this->dropColumns = array_merge($this->dropColumns, (array) $columns);
    }

    // ── INTERNAL ────────────────────────────────────────────

    protected function addColumn(string $name, string $type, array $parameters = []): ColumnDefinition
    {
        $col = new ColumnDefinition($name, $type, $parameters);
        $this->columns[] = $col;
        return $col;
    }
}
