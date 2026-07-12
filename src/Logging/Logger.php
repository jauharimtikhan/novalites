<?php

namespace Novalites\Logging;

class Logger
{
    protected static string $path;

    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, '/');

        if (!is_dir(self::$path)) {
            mkdir(self::$path, 0755, true);
        }
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    protected static function write(string $level, string $message, array $context): void
    {
        if (!isset(self::$path)) {
            self::$path = defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : sys_get_temp_dir();
            if (!is_dir(self::$path)) {
                mkdir(self::$path, 0755, true);
            }
        }

        $file = self::$path . '/' . date('Y-m-d') . '.log';

        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function exception(\Throwable $e, array $extra = []): void
    {
        self::write('EXCEPTION', $e->getMessage(), array_merge([
            'class' => get_class($e),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ], $extra));
    }
}
