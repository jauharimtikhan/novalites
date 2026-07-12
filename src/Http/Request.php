<?php

namespace Novalites\Http;

// use Novalites\Concern\UseAuthUserRequest;
use Novalites\Concern\HasAuthUser;
use Novalites\FileUploader\UploadedFile;

class Request
{
    use HasAuthUser;
    protected array $get = [];
    protected array $post = [];
    protected array $files = [];
    protected array $headers = [];
    protected ?array $jsonCache = null;
    protected bool $jsonParsed = false;
    protected array $customData = [];
    private  array $server = [];

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->server = $_SERVER;
        $this->headers = $this->parseHeaders();
    }

    public function __get(string $name): mixed
    {
        // Cek custom/override dulu, baru fallback ke input asli
        if (array_key_exists($name, $this->customData)) {
            return $this->customData[$name];
        }

        return $this->input($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->customData[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->customData) || $this->has($name);
    }

    public function __unset(string $name): void
    {
        unset($this->customData[$name]);
    }

    // ---------- URI / Method / Host ----------

    public function uri(): string
    {
        return strtok($this->server['REQUEST_URI'] ?? '/', '?');
    }

    public function fullUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    public function isPut(): bool
    {
        return $this->isMethod('PUT');
    }

    public function isDelete(): bool
    {
        return $this->isMethod('DELETE');
    }

    public function isPatch(): bool
    {
        return $this->isMethod('PATCH');
    }

    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? jtech_env('APP_URL') ?? 'localhost';
    }

    public function ip(): string
    {
        // Cek header proxy dulu, fallback ke REMOTE_ADDR
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'] as $key) {
            if (!empty($this->server[$key])) {
                $ips = explode(',', $this->server[$key]);
                return trim($ips[0]);
            }
        }
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): ?string
    {
        return $this->server['HTTP_USER_AGENT'] ?? null;
    }

    public function isSecure(): bool
    {
        if (!empty($this->server['HTTPS']) && strtolower($this->server['HTTPS']) !== 'off') {
            return true;
        }
        if (($this->server['SERVER_PORT'] ?? null) == 443) {
            return true;
        }
        if (
            !empty($this->server['HTTP_X_FORWARDED_PROTO'])
            && strtolower($this->server['HTTP_X_FORWARDED_PROTO']) === 'https'
        ) {
            return true;
        }
        return false;
    }

    public function scheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    public function url(): string
    {
        return $this->scheme() . '://' . $this->host() . $this->uri();
    }

    public function fullUrl(): string
    {
        return $this->scheme() . '://' . $this->host() . $this->fullUri();
    }

    // ---------- Headers ----------

    protected function parseHeaders(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($name)] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace('_', '-', $key);
                $headers[strtolower($name)] = $value;
            }
        }
        return $headers;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    public function contentType(): ?string
    {
        return $this->header('content-type');
    }

    public function isJson(): bool
    {
        return str_contains($this->contentType() ?? '', '/json')
            || str_contains($this->contentType() ?? '', '+json');
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('accept', '');
        return str_contains($accept, '/json') || $this->isJson() || $this->isAjax();
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    // ---------- Body parsing (cached, biar php://input ga dibaca berkali-kali) ----------

    protected function jsonBody(): array
    {
        if (!$this->jsonParsed) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '[]', true);
            $this->jsonCache = is_array($decoded) ? $decoded : [];
            $this->jsonParsed = true;
        }
        return $this->jsonCache;
    }

    // ---------- Input access ----------

    public function all(): array
    {
        return [...$this->jsonBody(), ...$this->post, ...$this->get];
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $data = $this->all();
        return $data[$key] ?? $default;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->get;
        }
        return $this->get[$key] ?? $default;
    }

    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->get;
        }
        return $this->get[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        $data = $this->all();
        return array_intersect_key($data, array_flip($keys));
    }

    public function except(array $keys): array
    {
        $data = $this->all();
        return array_diff_key($data, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function hasAny(array $keys): bool
    {
        $data = $this->all();
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }
        return false;
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '';
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function integer(string $key, int $default = 0): int
    {
        return (int) $this->input($key, $default);
    }

    // ---------- File uploads ----------
    public function file(string $key): UploadedFile|array|null
    {
        if (!isset($this->files[$key])) {
            return null;
        }

        $file = $this->files[$key];

        // Kasus multiple file: <input type="file" name="photos[]" multiple>
        // $_FILES['photos']['name'] jadi array
        if (is_array($file['name'])) {
            $result = [];
            $count = count($file['name']);

            for ($i = 0; $i < $count; $i++) {
                $result[] = UploadedFile::fromArray([
                    'name'     => $file['name'][$i],
                    'type'     => $file['type'][$i],
                    'tmp_name' => $file['tmp_name'][$i],
                    'error'    => $file['error'][$i],
                    'size'     => $file['size'][$i],
                ]);
            }

            return $result;
        }

        return UploadedFile::fromArray($file);
    }

    public function hasFile(string $key): bool
    {
        if (!isset($this->files[$key])) {
            return false;
        }

        $file = $this->files[$key];
        $error = is_array($file['error']) ? $file['error'][0] : $file['error'];

        return $error !== UPLOAD_ERR_NO_FILE;
    }

    // ---------- Validation (integrasi sama ValidatorFactory kemarin) ----------

    public function validate(array $rules, array $messages = [], array $attributes = []): array
    {
        return validate_or_fail($this->all(), $rules, $messages, $attributes);
    }

    // ---------- Singleton-ish instance ----------

    public static function getInstance(): self
    {
        return (new self);
    }
}
