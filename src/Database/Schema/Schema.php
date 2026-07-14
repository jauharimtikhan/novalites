<?php

namespace Novalites\Database\Schema;

use Novalites\Database\Manager;
use Novalites\Database\Schema\Grammars\Grammar;
use Novalites\Database\Schema\Grammars\MySqlGrammar;
use Novalites\Database\Schema\Grammars\PostgresGrammar;
use Novalites\Database\Schema\Grammars\SQLiteGrammar;
use RuntimeException;

class Schema
{
    public static function create(string $table, callable $callback, ?string $connection = null): void
    {
        $blueprint = new Blueprint($table, 'create');
        $callback($blueprint);

        $grammar = static::getGrammar($connection);
        $sql = $grammar->compileCreate($blueprint);

        Manager::statement($sql, [], $connection);
    }

    public static function table(string $table, callable $callback, ?string $connection = null): void
    {
        $blueprint = new Blueprint($table, 'alter');
        $callback($blueprint);

        $grammar = static::getGrammar($connection);
        $statements = $grammar->compileAlter($blueprint);

        foreach ($statements as $sql) {
            Manager::statement($sql, [], $connection);
        }
    }

    public static function drop(string $table, ?string $connection = null): void
    {
        $grammar = static::getGrammar($connection);
        Manager::statement($grammar->compileDrop($table), [], $connection);
    }

    public static function dropIfExists(string $table, ?string $connection = null): void
    {
        $grammar = static::getGrammar($connection);
        Manager::statement($grammar->compileDropIfExists($table), [], $connection);
    }

    public static function hasTable(string $table, ?string $connection = null): bool
    {
        $driver = Manager::getDriverName($connection);

        $sql = match ($driver) {
            'mysql'  => "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
            'pgsql'  => "SELECT 1 FROM information_schema.tables WHERE table_name = ?",
            'sqlite' => "SELECT 1 FROM sqlite_master WHERE type='table' AND name = ?",
            default  => throw new RuntimeException("hasTable() not supported for driver [{$driver}]"),
        };

        $result = Manager::raw($sql, [$table], $connection);
        return !empty($result);
    }

    protected static function getGrammar(?string $connection): Grammar
    {
        $driver = Manager::getDriverName($connection);

        return match ($driver) {
            'mysql'  => new MySqlGrammar(),
            'pgsql'  => new PostgresGrammar(),
            'sqlite' => new SQLiteGrammar(),
            default  => throw new RuntimeException("No schema grammar available for driver [{$driver}]"),
        };
    }

    /**
     * Drop semua tabel di database aktif. Berguna buat testing/development
     * (fresh migrate), setara `php artisan db:wipe` di Laravel.
     */
    public static function dropAllTables(?string $connection = null): void
    {
        $driver = Manager::getDriverName($connection);

        match ($driver) {
            'mysql'  => static::dropAllTablesMysql($connection),
            'pgsql'  => static::dropAllTablesPostgres($connection),
            'sqlite' => static::dropAllTablesSqlite($connection),
            default  => throw new RuntimeException("dropAllTables() not supported for driver [{$driver}]"),
        };
    }

    protected static function dropAllTablesMysql(?string $connection): void
    {
        $tables = Manager::raw(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()",
            [],
            $connection
        );

        if (empty($tables)) {
            return;
        }

        // matiin FK check dulu biar urutan drop ga masalah walau saling reference
        Manager::statement('SET FOREIGN_KEY_CHECKS = 0', [], $connection);

        foreach ($tables as $row) {
            $name = $row['table_name'] ?? $row['TABLE_NAME'];
            Manager::statement("DROP TABLE IF EXISTS {$name}", [], $connection);
        }

        Manager::statement('SET FOREIGN_KEY_CHECKS = 1', [], $connection);
    }

    protected static function dropAllTablesPostgres(?string $connection): void
    {
        $tables = Manager::raw(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public'",
            [],
            $connection
        );

        if (empty($tables)) {
            return;
        }

        foreach ($tables as $row) {
            // CASCADE biar tabel yang di-reference FK tabel lain tetap ke-drop tanpa error
            Manager::statement("DROP TABLE IF EXISTS {$row['tablename']} CASCADE", [], $connection);
        }
    }

    protected static function dropAllTablesSqlite(?string $connection): void
    {
        $tables = Manager::raw(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
            [],
            $connection
        );

        if (empty($tables)) {
            return;
        }

        // sqlite: matiin FK constraint sementara biar urutan drop bebas
        Manager::statement('PRAGMA foreign_keys = OFF', [], $connection);

        foreach ($tables as $row) {
            Manager::statement("DROP TABLE IF EXISTS {$row['name']}", [], $connection);
        }

        Manager::statement('PRAGMA foreign_keys = ON', [], $connection);
    }
}
