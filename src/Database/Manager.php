<?php

namespace Novalites\Database;

use PDO;
use PDOException;
use RuntimeException;
use Novalites\Database\QueryBuilder;

class Manager
{
    /** @var array<string, PDO> pool koneksi aktif, key = nama connection */
    protected static array $connections = [];

    /** @var array<string, array> config semua connection, di-load sekali */
    protected static array $config = [];

    protected static ?string $defaultConnection = null;

    protected static bool $inited = false;

    // ── BOOTSTRAP ─────────────────────────────────────────

    public static function init(?array $config = null): void
    {
        if (static::$inited && $config === null) {
            return;
        }

        $config ??= require static::resolveConfigPath();

        static::$config = $config['connections'] ?? [];
        static::$defaultConnection = $config['default'] ?? array_key_first(static::$config);
        static::$inited = true;
    }

    protected static function resolveConfigPath(): string
    {
        $path = defined('BASE_PATH')
            ? constant('BASE_PATH') . '/config/database.php'
            : dirname(__DIR__) . '/config/database.php';

        if (!file_exists($path)) {
            throw new RuntimeException("Database config file not found at: {$path}");
        }

        return $path;
    }

    // ── CONNECTION RESOLVER ───────────────────────────────

    public static function getConnection(?string $name = null): PDO
    {
        static::init();

        $name ??= static::$defaultConnection;

        if (!isset(static::$connections[$name])) {
            static::$connections[$name] = static::makeConnection($name);
        }

        return static::$connections[$name];
    }

    /**
     * Alias biar kebaca natural: Database::connection('pgsql')
     */
    public static function connection(?string $name = null): PDO
    {
        return static::getConnection($name);
    }

    protected static function makeConnection(string $name): PDO
    {
        if (!isset(static::$config[$name])) {
            throw new RuntimeException("Database connection [{$name}] is not configured.");
        }

        $config = static::$config[$name];
        $driver = $config['driver'] ?? throw new RuntimeException("Driver not set for connection [{$name}].");

        $dsn = match ($driver) {
            'mysql'  => static::mysqlDsn($config),
            'pgsql'  => static::pgsqlDsn($config),
            'sqlite' => static::sqliteDsn($config),
            'sqlsrv' => static::sqlsrvDsn($config),
            default  => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = $driver === 'sqlite'
                ? new PDO($dsn, null, null, $options)
                : new PDO($dsn, $config['username'] ?? null, $config['password'] ?? null, $options);
        } catch (PDOException $e) {
            throw new PDOException(
                "Could not connect to database [{$name}] ({$driver}): " . $e->getMessage(),
                (int) $e->getCode()
            );
        }

        static::applyPostConnectSettings($pdo, $driver, $config);

        return $pdo;
    }

    // ── DSN BUILDERS PER DRIVER ───────────────────────────

    protected static function mysqlDsn(array $config): string
    {
        $host    = $config['host'] ?? '127.0.0.1';
        $port    = $config['port'] ?? 3306;
        $db      = $config['database'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        // support unix socket kalau disediakan
        if (!empty($config['unix_socket'])) {
            return "mysql:unix_socket={$config['unix_socket']};dbname={$db};charset={$charset}";
        }

        return "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    }

    protected static function pgsqlDsn(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 5432;
        $db   = $config['database'] ?? '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$db}";

        if (!empty($config['sslmode'])) {
            $dsn .= ";sslmode={$config['sslmode']}";
        }

        return $dsn;
    }

    protected static function sqliteDsn(array $config): string
    {
        $path = $config['database'] ?? ':memory:';
        return "sqlite:{$path}";
    }

    protected static function sqlsrvDsn(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 1433;
        $db   = $config['database'] ?? '';

        return "sqlsrv:Server={$host},{$port};Database={$db}";
    }

    protected static function applyPostConnectSettings(PDO $pdo, string $driver, array $config): void
    {
        if ($driver === 'mysql' && !empty($config['collation'])) {
            $pdo->exec("SET NAMES '{$config['charset']}' COLLATE '{$config['collation']}'");
        }

        if ($driver === 'pgsql' && !empty($config['schema'])) {
            $pdo->exec("SET search_path TO {$config['schema']}");
        }

        if ($driver === 'sqlite') {
            // aktifin foreign key constraint, defaultnya OFF di sqlite
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    // ── QUERY BUILDER ENTRYPOINT (mirip DB::table() Laravel) ──

    public static function table(string $table, ?string $connection = null): QueryBuilder
    {
        return new QueryBuilder(static::getConnection($connection), $table);
    }

    public static function raw(string $sql, array $bindings = [], ?string $connection = null): array
    {
        $stmt = static::getConnection($connection)->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    public static function statement(string $sql, array $bindings = [], ?string $connection = null): bool
    {
        $stmt = static::getConnection($connection)->prepare($sql);
        return $stmt->execute($bindings);
    }

    // ── TRANSACTION HELPERS ───────────────────────────────

    public static function beginTransaction(?string $connection = null): void
    {
        static::getConnection($connection)->beginTransaction();
    }

    public static function commit(?string $connection = null): void
    {
        static::getConnection($connection)->commit();
    }

    public static function rollBack(?string $connection = null): void
    {
        static::getConnection($connection)->rollBack();
    }

    /**
     * Wrap closure dalam transaction, auto commit/rollback.
     * Support nested call (pakai savepoint counter sederhana).
     */
    public static function transaction(callable $callback, ?string $connection = null): mixed
    {
        $pdo = static::getConnection($connection);
        $alreadyInTransaction = $pdo->inTransaction();

        if (!$alreadyInTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);

            if (!$alreadyInTransaction) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if (!$alreadyInTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // ── CONNECTION MANAGEMENT ─────────────────────────────

    public static function purge(?string $name = null): void
    {
        static::init();
        $name ??= static::$defaultConnection;
        unset(static::$connections[$name]);
    }

    public static function reconnect(?string $name = null): PDO
    {
        static::purge($name);
        return static::getConnection($name);
    }

    public static function setDefaultConnection(string $name): void
    {
        static::init();
        static::$defaultConnection = $name;
    }

    public static function getDefaultConnectionName(): string
    {
        static::init();
        return static::$defaultConnection;
    }

    public static function addConnection(string $name, array $config): void
    {
        static::init();
        static::$config[$name] = $config;
    }

    public static function getDriverName(?string $name = null): string
    {
        static::init();
        $name ??= static::$defaultConnection;
        return static::$config[$name]['driver'] ?? throw new RuntimeException("Connection [{$name}] not found.");
    }
}
