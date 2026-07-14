<?php

namespace Novalites\Database;

use Novalites\Database\Pagination\Paginator;
use Novalites\Database\Pagination\SimplePaginator;
use Novalites\Support\Collection;
use PDO;
use PDOStatement;

class QueryBuilder
{
    protected PDO $pdo;
    protected string $table;
    protected array $columns = ['*'];
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $orders = [];
    protected array $joins = [];
    protected ?int $limit = null;
    protected ?int $offset = null;

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function select(array|string $columns = ['*']): static
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    // ── WHERE ────────────────────────────────────────────

    public function where(string $column, string $operator, mixed $value = null): static
    {
        // support where('col', $value) -> default operator '='
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'boolean'  => empty($this->wheres) ? '' : 'AND',
            'sql'      => "{$column} {$operator} ?",
        ];
        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'boolean'  => empty($this->wheres) ? '' : 'OR',
            'sql'      => "{$column} {$operator} ?",
        ];
        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $this->wheres[] = [
            'boolean' => empty($this->wheres) ? '' : 'AND',
            'sql'     => "{$column} IN ({$placeholders})",
        ];
        array_push($this->bindings, ...$values);

        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = [
            'boolean' => empty($this->wheres) ? '' : 'AND',
            'sql'     => "{$column} IS NULL",
        ];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = [
            'boolean' => empty($this->wheres) ? '' : 'AND',
            'sql'     => "{$column} IS NOT NULL",
        ];
        return $this;
    }

    // ── JOIN ─────────────────────────────────────────────

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $this->joins[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    // ── ORDER / LIMIT ────────────────────────────────────

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = "{$column} " . strtoupper($direction);
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    public function paginate(int $perPage = 15, ?int $page = null, string $pageName = 'page'): Paginator
    {
        $page ??= Paginator::resolveCurrentPage($pageName);

        // hitung total dulu SEBELUM limit/offset di-apply,
        // supaya query count ga ikut kepotong LIMIT
        $total = $this->count();

        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;

        $items = $this->get(); // manggil get() versi override kalau dipanggil dari ModelQueryBuilder

        return new Paginator($items->toArray(), $total, $perPage, $page, $pageName);
    }

    public function simplePaginate(int $perPage = 15, ?int $page = null, string $pageName = 'page'): SimplePaginator
    {
        $page ??= Paginator::resolveCurrentPage($pageName);

        // ambil 1 row ekstra buat ngecek apakah masih ada halaman berikutnya,
        // tanpa perlu query COUNT(*) yang mahal di tabel besar
        $this->limit = $perPage + 1;
        $this->offset = ($page - 1) * $perPage;

        $items = $this->get();

        $hasMorePages = count($items) > $perPage;

        if ($hasMorePages) {
            $newItems =  $items->toArray();
            array_pop($newItems); // buang row ekstra, ga usah ditampilkan
        }

        return new SimplePaginator($items->toArray(), $perPage, $page, $hasMorePages);
    }

    // ── EXECUTORS ────────────────────────────────────────

    public function get(): Collection
    {
        $stmt = $this->runSelect();
        return new Collection($stmt->fetchAll());
    }

    public function first()
    {
        $this->limit = 1;
        $stmt = $this->runSelect();
        return $stmt->fetch();
    }

    public function find(int|string $id, string $primaryKey = 'id')
    {
        return $this->where($primaryKey, '=', $id)->first();
    }

    public function count(): int
    {
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) as aggregate'];
        $stmt = $this->runSelect();
        $this->columns = $originalColumns;

        $result = $stmt->fetch();
        return (int) $result['aggregate'];
    }

    public function insert(array $data): string|false
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return $this->pdo->lastInsertId();
    }

    public function update(array $data): int
    {
        $set = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $sql = "UPDATE {$this->table} SET {$set}" . $this->buildWhereClause();

        $bindings = array_merge(array_values($data), $this->bindings);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}" . $this->buildWhereClause();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        return $stmt->rowCount();
    }

    // ── QUERY BUILDING ───────────────────────────────────

    protected function runSelect(): PDOStatement
    {
        $sql = $this->toSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt;
    }

    public function toSql(): string
    {
        $columns = implode(', ', $this->columns);
        $sql = "SELECT {$columns} FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->buildWhereClause();

        if (!empty($this->orders)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    protected function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $clause = ' WHERE ';
        foreach ($this->wheres as $i => $where) {
            $clause .= ($i === 0 ? '' : " {$where['boolean']} ") . $where['sql'];
        }

        return $clause;
    }

    // ── PLUCK ─────────────────────────────────────────────

    /**
     * Ambil satu kolom aja dari semua row jadi array flat.
     * pluck('name') -> ['Jauhar', 'Budi', 'Siti']
     * pluck('name', 'id') -> [1 => 'Jauhar', 2 => 'Budi', 3 => 'Siti'] (key => value)
     */
    public function pluck(string $column, ?string $key = null): Collection
    {
        $originalColumns = $this->columns;
        $this->columns = $key !== null ? [$column, $key] : [$column];

        $stmt = $this->runSelect();
        $rows = $stmt->fetchAll();

        $this->columns = $originalColumns;

        if ($key !== null) {
            $result = [];
            foreach ($rows as $row) {
                $result[$row[$key]] = $row[$column];
            }
            return new Collection($result);
        }

        return new Collection(array_map(fn($row) => $row[$column], $rows));
    }

    // ── AGGREGATES ────────────────────────────────────────

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    public function sum(string $column): mixed
    {
        return $this->aggregate('SUM', $column) ?? 0;
    }

    public function avg(string $column): mixed
    {
        return $this->aggregate('AVG', $column);
    }

    protected function aggregate(string $function, string $column): mixed
    {
        $originalColumns = $this->columns;
        $this->columns = ["{$function}({$column}) as aggregate"];

        $stmt = $this->runSelect();
        $result = $stmt->fetch();

        $this->columns = $originalColumns;

        return $result['aggregate'] ?? null;
    }

// ── EXISTS ────────────────────────────────────────────

    /**
     * Cek apakah ada minimal 1 row yang match kondisi where,
     * tanpa perlu fetch semua data — pakai LIMIT 1 biar ringan.
     */
    public function exists(): bool
    {
        $originalColumns = $this->columns;
        $originalLimit = $this->limit;

        $this->columns = ['1 as exists_check'];
        $this->limit = 1;

        $stmt = $this->runSelect();
        $result = $stmt->fetch();

        $this->columns = $originalColumns;
        $this->limit = $originalLimit;

        return $result !== false;
    }

    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Hapus semua row di tabel dan reset auto-increment ke awal.
     * Beda dari delete() biasa: truncate ga bisa di-rollback dalam
     * transaction di MySQL (implicit commit), dan lebih cepat karena
     * ga log per-row kayak DELETE biasa.
     */
    public function truncate(): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'sqlite' => $this->truncateSqlite(),
            'pgsql'  => $this->truncatePostgres(),
            default  => $this->truncateDefault(), // mysql, sqlsrv, dll yang support TRUNCATE TABLE standar
        };
    }

    protected function truncateDefault(): bool
    {
        return $this->pdo->exec("TRUNCATE TABLE {$this->table}") !== false;
    }

    protected function truncatePostgres(): bool
    {
        // RESTART IDENTITY biar sequence/auto-increment ikut ke-reset,
        // CASCADE biar row di tabel lain yang foreign key ke sini ikut ke-handle
        return $this->pdo->exec("TRUNCATE TABLE {$this->table} RESTART IDENTITY CASCADE") !== false;
    }

    protected function truncateSqlite(): bool
    {
        // SQLite ga punya statement TRUNCATE, jadi manual: hapus semua row
        // lalu reset counter auto-increment di tabel internal sqlite_sequence
        $this->pdo->exec("DELETE FROM {$this->table}");
        $this->pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$this->table}'");

        return true;
    }

    /**
     * Update row yang match $conditions kalau ada, atau insert row baru
     * kalau ga ketemu. Mirip Illuminate: DB::table()->updateOrInsert().
     *
     * @param array $conditions kondisi buat where(), dipakai juga sebagai bagian data pas insert
     * @param array $values     data yang mau di-update/insert (di luar kondisi)
     */
    public function updateOrInsert(array $conditions, array $values = []): bool
    {
        // apply semua condition sebagai where(), support multiple kolom sekaligus
        foreach ($conditions as $column => $value) {
            $this->where($column, '=', $value);
        }

        if ($this->exists()) {
            // reset wheres yg udah kepake exists() supaya bindings ga dobel pas update()
            // (karena where() di atas udah nambah ke $this->wheres & $this->bindings sekali,
            // update() bakal pakai ulang state yang sama, jadi aman ga perlu re-apply where)
            return (bool) $this->update($values);
        }

        return (bool) $this->insert(array_merge($conditions, $values));
    }
}
