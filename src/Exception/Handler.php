<?php

namespace Novalites\Exception;

use Error;
use ErrorException;
use Illuminate\Database\QueryException;
use Illuminate\Database\RecordNotFoundException;
use Throwable;
use Novalites\Http\Response;
use Novalites\Logging\Logger;
use PDOException;

class Handler
{
    public static function register(): void
    {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $level)) {
            return false;
        }
        throw new ErrorException($message, 0, $level, $file, $line);
    }

    public static function handleException(Throwable $e): void
    {
        self::render($e);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::render(new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    protected static function render(Throwable $e): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        self::logException($e);

        [$status, $message] = self::classify($e);

        $debug = self::isDebugMode();

        if (self::wantsJson()) {
            self::renderJson($e, $status, $message, $debug);
        } else {
            self::renderHtml($e, $status, $message, $debug);
        }
    }

    /**
     * Deteksi apakah request ini butuh JSON (API) atau HTML (web).
     */
    protected static function wantsJson(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        if (str_starts_with(ltrim($uri, '/'), 'api/')) {
            return true;
        }

        if (str_contains($accept, 'application/json')) {
            return true;
        }

        if (strtolower($xRequestedWith) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }

    protected static function renderJson(Throwable $e, int $status, string $message, bool $debug): void
    {
        $payload = [
            'success' => false,
            'message' => $debug ? $e->getMessage() : self::safeMessage($status, $message),
        ];

        if ($debug) {
            $payload['debug'] = [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => explode("\n", $e->getTraceAsString()),
            ];

            // Tambahin detail query KHUSUS mode debug, biar developer langsung liat query yang gagal
            if ($e instanceof QueryException) {
                $payload['debug']['sql'] = $e->getSql();
                $payload['debug']['bindings'] = self::maskSensitiveBindings($e->getSql(), $e->getBindings());
            }
        }

        Response::json($payload, $status);
    }

    protected static function renderHtml(Throwable $e, int $status, string $message, bool $debug): void
    {
        $viewData = [
            'status'  => $status,
            'message' => $debug ? $message : self::safeMessage($status, $message),
            'debug'   => $debug ? [
                'exception' => get_class($e),
                'file'      => str_replace('illuminate', 'jtech', $e->getFile()),
                'line'      => $e->getLine(),
                'trace'     => str_replace('illuminate', 'jtech', $e->getTraceAsString()),
            ] : null,
        ];

        // Prioritas 1: user punya view custom spesifik per status (errors/404.tpl.php, dll)
        // Prioritas 2: user punya view custom generic (errors/generic.tpl.php)
        foreach (["errors.{$status}", 'errors.generic'] as $viewName) {
            try {
                if (self::viewExists($viewName)) {
                    Response::view($viewName, $viewData, $status)->send();
                    return;
                }
            } catch (Throwable) {
                // TemplateEngine gagal compile/render -> lanjut ke fallback berikutnya,
                // JANGAN biarin error di sini nyebabin infinite loop / blank page
                break;
            }
        }
        if (!$debug) {
            if ($e instanceof QueryException) {
                $viewData['debug']['sql'] = $e->getSql();
                $viewData['debug']['bindings'] = self::maskSensitiveBindings($e->getSql(), $e->getBindings());
            }
        }
        // Prioritas 3 (fallback terakhir): plain PHP, TANPA TemplateEngine sama sekali.
        // Ini SELALU jalan apapun kondisinya, karena ga gantung ke compiler/cache manapun.
        self::renderFallbackView($viewData, $status);
    }

    protected static function renderFallbackView(array $data, int $status): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }

        extract($data);

        $fallbackPath = __DIR__ . '/views/fallback-error.php';

        if (file_exists($fallbackPath)) {
            include $fallbackPath;
        } else {
            // Fallback dari fallback — kalau sampai file ini pun ilang, minimal ada teks
            echo "<h1>{$status}</h1><p>" . htmlspecialchars($message ?? 'Error', ENT_QUOTES) . "</p>";
        }

        exit;
    }

    protected static function isDebugMode(): bool
    {
        // Coba env() dulu (normal case, .env udah ke-load)
        try {
            $value = env('APP_DEBUG', null);
            if ($value !== null) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        } catch (Throwable) {
            // env()/.env belum siap -> lanjut ke fallback di bawah
        }

        // Fallback: baca langsung dari environment variable OS,
        // atau baca file .env manual tanpa lewat Dotenv parser (biar ga ikut gagal kalau .env rusak)
        $raw = getenv('APP_DEBUG');
        if ($raw !== false) {
            return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        // Terakhir: coba grep manual dari file .env tanpa parsing penuh
        $envPath = defined('BASE_PATH') ? BASE_PATH . '/.env' : null;
        if ($envPath && file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                if (preg_match('/^APP_DEBUG\s*=\s*"?(\w+)"?/', $line, $m)) {
                    return filter_var($m[1], FILTER_VALIDATE_BOOLEAN);
                }
            }
        }

        // Default paling aman: debug OFF (biar ga bocorin info sensitif kalau ga yakin)
        return false;
    }

    protected static function viewExists(string $view): bool
    {
        $path = str_replace('.', '/', $view);
        return file_exists(BASE_PATH . "/resources/views/{$path}.tpl.php");
    }

    /**
     * Pesan aman buat production (ga bocorin detail internal kalau bukan HttpException).
     */
    protected static function safeMessage(int $status, string $originalMessage): string
    {
        // Status yang MEMANG didesain buat ditampilin ke user (termasuk hasil classify() DB di atas)
        if (in_array($status, [400, 401, 403, 404, 405, 409, 422, 429, 503], true)) {
            return $originalMessage;
        }

        // 500 dan lainnya -> generic, JANGAN pernah bocorin raw SQL/exception message
        return 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';
    }

    /**
     * Tentukan status code & pesan yang sesuai jenis exception-nya.
     */
    protected static function classify(Throwable $e): array
    {
        if ($e instanceof HttpException) {
            return [$e->getStatusCode(), $e->getMessage()];
        }
        if ($e instanceof Error) {
            return [589, $e->getMessage()];
        }

        if ($e instanceof RecordNotFoundException) {
            return [580, $e->getMessage()];
        }

        if ($e instanceof QueryException || $e instanceof PDOException) {
            return self::classifyDatabaseException($e);
        }

        return [500, $e->getMessage()];
    }

    protected static function classifyDatabaseException(Throwable $e): array
    {
        // Ambil SQLSTATE code — 2 digit pertama dari kode error PDO
        $code = $e instanceof QueryException
            ? ($e->errorInfo[0] ?? $e->getCode())
            : $e->getCode();

        $message = $e->getMessage();


        return match (true) {
            // Unique constraint violation (duplicate entry)
            $code === '23000' || str_contains($message, 'Duplicate entry')
            => [409, 'Data yang kamu kirim sudah ada sebelumnya (duplikat).'],

            // Foreign key constraint violation
            str_contains($message, 'foreign key constraint')
            => [422, 'Data terkait dengan record lain, tidak bisa diproses.'],

            // Koneksi database gagal/putus
            $code === '2002' || $code === 'HY000' && str_contains($message, 'Connection refused')
            => [503, 'Layanan database sedang tidak tersedia. Coba lagi nanti.'],

            // Query timeout
            str_contains($message, 'timeout') || str_contains($message, 'Lock wait timeout')
            => [503, 'Permintaan memakan waktu terlalu lama. Coba lagi nanti.'],

            // Syntax error di query (bug developer, bukan salah user)
            $code === '42S02'
            => [400, $message ?? 'Terjadi kesalahan pada server.'],
            $code === '42000' || $code === '42S02'
            => [500, 'Terjadi kesalahan pada server.'],

            default => [500, 'Terjadi kesalahan pada server.'],
        };
    }

    /**
     * Log exception dengan detail EKSTRA khusus buat database error
     * (SQL query + bindings), TERPISAH dari log umum biar gampang di-grep.
     */
    protected static function logException(Throwable $e): void
    {
        if ($e instanceof QueryException) {
            Logger::error('Database query gagal', [
                'sql'      => $e->getSql(),
                'bindings' => self::maskSensitiveBindings($e->getSql(), $e->getBindings()),
                'message'  => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
            ]);
            return;
        }

        Logger::exception($e);
    }

    /**
     * Mask value binding yang kemungkinan sensitif (password, token, dll)
     * berdasarkan nama kolom yang match di query SQL-nya.
     */
    protected static function maskSensitiveBindings(string $sql, array $bindings): array
    {
        $sensitiveKeywords = ['password', 'token', 'secret', 'api_key'];

        // Deteksi sederhana: kalau SQL-nya nyebut kolom sensitif, mask binding yang posisinya berdekatan
        foreach ($bindings as $key => $value) {
            foreach ($sensitiveKeywords as $keyword) {
                if (stripos($sql, $keyword) !== false && is_string($value) && strlen($value) > 10) {
                    $bindings[$key] = '***MASKED***';
                    break;
                }
            }
        }

        return $bindings;
    }
}
