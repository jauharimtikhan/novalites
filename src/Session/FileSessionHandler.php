<?php

namespace Novalites\Session;

class FileSessionHandler implements SessionHandlerContract
{
    protected bool $started = false;

    public function __construct(protected array $config = [])
    {
        $path = $config['path'] ?? sys_get_temp_dir() . '/jtech_sessions';

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        session_save_path($path);

        session_name($config['cookie_name'] ?? 'jtech_session');

        session_set_cookie_params([
            'lifetime' => ($config['lifetime'] ?? 120) * 60,
            'path'     => '/',
            'domain'   => $config['domain'] ?? '',
            'secure'   => $config['secure'] ?? false,
            'httponly' => true,
            'samesite' => $config['same_site'] ?? 'Lax',
        ]);
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_start();
        $this->started = true;

        // Regenerate ID berkala biar ga rawan session fixation attack
        $this->regenerateIfNeeded();
    }

    protected function regenerateIfNeeded(): void
    {
        $interval = 1800; // 30 menit
        $last = $_SESSION['_last_regenerate'] ?? null;

        if ($last === null) {
            $_SESSION['_last_regenerate'] = time();
            return;
        }

        if (time() - $last > $interval) {
            session_regenerate_id(true);
            $_SESSION['_last_regenerate'] = time();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function all(): array
    {
        return $_SESSION;
    }

    public function flush(): void
    {
        $_SESSION = [];
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function getId(): string
    {
        return session_id();
    }

    public function save(): void
    {
        // Native session udah auto-persist, ga perlu manual save.
        // Method ini ada biar konsisten sama interface (driver database butuh ini).
    }
}
