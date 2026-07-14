<?php

namespace Novalites\Session;

use Novalites\Database\Manager;

class DatabaseSessionHandler implements SessionHandlerContract
{
    protected string $id;
    protected array $data = [];
    protected bool $started = false;
    protected int $lifetimeMinutes;
    protected string $cookieName;

    public function __construct(protected array $config = [])
    {
        $this->lifetimeMinutes = $config['lifetime'] ?? 120;
        $this->cookieName = $config['cookie_name'] ?? 'jtech_session';
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->id = $_COOKIE[$this->cookieName] ?? $this->generateId();

        $this->loadFromDatabase();

        $this->started = true;

        $this->setCookie();

        $this->gc(); // bersihin session expired, dipanggil sesekali
    }

    protected function generateId(): string
    {
        return bin2hex(random_bytes(32));
    }

    protected function loadFromDatabase(): void
    {
        $row = Manager::table('sessions')->where('id', $this->id)->first();

        $expiredThreshold = time() - ($this->lifetimeMinutes * 60);

        if ($row === null || $row->last_activity < $expiredThreshold) {
            // Session ga ada / udah expired -> mulai session baru
            $this->id = $this->generateId();
            $this->data = [];
            return;
        }

        $this->data = json_decode($row->payload, true) ?? [];
    }

    protected function setCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie($this->cookieName, $this->id, [
            'expires'  => time() + ($this->lifetimeMinutes * 60),
            'path'     => '/',
            'domain'   => $this->config['domain'] ?? '',
            'secure'   => $this->config['secure'] ?? false,
            'httponly' => true,
            'samesite' => $this->config['same_site'] ?? 'Lax',
        ]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function flush(): void
    {
        $this->data = [];
    }

    public function regenerate(): void
    {
        Manager::table('sessions')->where('id', $this->id)->delete();
        $this->id = $this->generateId();
        $this->setCookie();
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * WAJIB dipanggil di akhir request buat persist data ke DB.
     */
    public function save(): void
    {
        Manager::table('sessions')->updateOrInsert(
            ['id' => $this->id],
            [
                'user_id'       => $this->data['_user_id'] ?? null,
                'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'payload'       => json_encode($this->data),
                'last_activity' => time(),
            ]
        );
    }

    protected function gc(): void
    {
        // 2% kemungkinan tiap request buat jalanin garbage collection, biar ga berat tiap request
        if (random_int(1, 100) <= 2) {
            $expiredThreshold = time() - ($this->lifetimeMinutes * 60);
            Manager::table('sessions')->where('last_activity', '<', $expiredThreshold)->delete();
        }
    }
}
