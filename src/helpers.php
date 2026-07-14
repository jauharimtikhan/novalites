<?php

use Carbon\Carbon;
use Novalites\Container\Container;
use Novalites\Http\Request;
use Novalites\Http\Response;
use Novalites\Security\CsrfToken;

function json(mixed $data, int $code = 200, ?array $headers = [])
{
    return Response::json($data, $code, $headers);
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        throw new \Novalites\Exception\HttpException($status, $message);
    }
}

if (!function_exists('request')) {
    function request()
    {
        return Request::getInstance();
    }
}



if (!function_exists('is_https')) {
    /**
     * Deteksi apakah request ini pakai HTTPS.
     * Ngecek langsung DAN header dari reverse proxy/load balancer
     * (Nginx, Apache proxy, Cloudflare, AWS ELB, dll)
     */
    function is_https(): bool
    {
        // 1. Cek langsung dari server
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        // 2. Cek port standar HTTPS
        if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
            return true;
        }

        // 3. Header dari reverse proxy (Nginx, Apache mod_proxy, dll)
        if (
            !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        ) {
            return true;
        }

        // 4. Header dari beberapa load balancer/proxy lain
        if (
            !empty($_SERVER['HTTP_X_FORWARDED_SSL'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on'
        ) {
            return true;
        }

        // 5. Cloudflare
        if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
            $cfVisitor = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
            if (isset($cfVisitor['scheme']) && $cfVisitor['scheme'] === 'https') {
                return true;
            }
        }

        // 6. Beberapa environment (mis. AWS ELB) pakai X-Forwarded-Port
        if (($_SERVER['HTTP_X_FORWARDED_PORT'] ?? null) == 443) {
            return true;
        }

        return false;
    }
}

if (!function_exists('base_url')) {
    /**
     * Dapatkan base URL aplikasi, otomatis https kalau didukung.
     *
     * @param string $path Path tambahan (opsional), otomatis di-trim slash-nya
     * @return string
     */
    function base_url(string $path = ''): string
    {
        $scheme = is_https() ? 'https' : 'http';

        // Host: prioritaskan X-Forwarded-Host (kalau di belakang proxy), fallback ke HTTP_HOST
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

        // Kalau ada multiple host (proxy chain), ambil yang pertama
        if (str_contains($host, ',')) {
            $host = trim(explode(',', $host)[0]);
        }

        $baseUrl = "{$scheme}://{$host}";

        if ($path !== '') {
            $baseUrl .= '/' . ltrim($path, '/');
        }

        return $baseUrl;
    }
}

if (!function_exists('current_url')) {
    /**
     * Dapatkan full URL request saat ini (termasuk query string).
     */
    function current_url(): bool|string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return base_url() . $uri;
    }
}

if (!function_exists('current_url_without_query')) {
    /**
     * Dapatkan URL saat ini TANPA query string.
     */
    function current_url_without_query(): string
    {
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        return base_url() . $uri;
    }
}

if (!function_exists('app_url')) {
    /**
     * Base URL dari .env / config (buat kondisi APP_URL di-set manual,
     * misal karena app di belakang proxy yang headernya ga reliable).
     * Fallback ke deteksi otomatis kalau APP_URL ga di-set.
     */
    function app_url(string $path = ''): string
    {
        $configured = $_ENV['APP_URL'] ?? null;

        if ($configured) {
            $base = rtrim($configured, '/');
            return $path !== '' ? $base . '/' . ltrim($path, '/') : $base;
        }

        return base_url($path);
    }
}

if (!function_exists('validate_or_fail')) {
    /**
     * Validasi data, throw ValidationException kalau gagal.
     * Mirip $request->validate() di Laravel.
     */
    function validate_or_fail(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = \Novalites\Validation\Validator::make($data, $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new \Novalites\Exception\ValidationException($validator->errors());
        }

        return $validator->validated();
    }
}

if (!function_exists('now')) {
    function now()
    {
        return Carbon::now(jtech_env('APP_TIMEZONE', 'Asia/Jakarta'));
    }
}

if (!function_exists('today')) {
    function today()
    {
        return Carbon::today(jtech_env('APP_TIMEZONE', 'Asia/Jakarta'));
    }
}

if (!function_exists('cb_parse')) {
    function cb_parse(string $text)
    {
        return Carbon::parse($text)->timezone(jtech_env('APP_TIMEZONE', 'Asia/Jakarta'));
    }
}


if (!function_exists('config')) {
    function config($key = null, $default = null)
    {
        static $repository = [];
        static $configPath = null;

        // 1. Logika Cerdas untuk Mencari Folder Config Parent Project
        if (is_null($configPath)) {
            if (defined('CONFIG_PATH')) {
                // Prioritas 1: Gunakan constant jika sudah didefinisikan oleh user
                $configPath = CONFIG_PATH;
            } else {
                // Prioritas 2: Auto-detect jika library berada di dalam folder 'vendor'
                // Kita cari posisi string '/vendor/' dari path file helper ini berada
                $vendorDir = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
                $vendorPos = strpos(__DIR__, $vendorDir);

                if ($vendorPos !== false) {
                    // Potong path sampai tepat sebelum kata '/vendor/'
                    $rootPath = substr(__DIR__, 0, $vendorPos);
                    $configPath = $rootPath . DIRECTORY_SEPARATOR . 'config';
                } else {
                    // Fallback: Jika tidak ada di folder vendor (misal saat library di-develop terpisah)
                    $configPath = __DIR__ . DIRECTORY_SEPARATOR . 'config';
                }
            }
        }

        // 2. Jika tidak ada parameter, kembalikan semua data
        if (is_null($key)) {
            return $repository;
        }

        // 3. Mode SET (jika parameter array)
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $parts = explode('.', $k);
                $file = array_shift($parts);

                if (!isset($repository[$file])) {
                    $file_path = $configPath . DIRECTORY_SEPARATOR . $file . '.php';
                    $repository[$file] = file_exists($file_path) ? include $file_path : [];
                }

                $array = &$repository[$file];
                foreach ($parts as $part) {
                    if (!isset($array[$part]) || !is_array($array[$part])) {
                        $array[$part] = [];
                    }
                    $array = &$array[$part];
                }
                $array = $v;
            }
            return true;
        }

        // 4. Mode GET (jika parameter string)
        $parts = explode('.', $key);
        $file = array_shift($parts);

        if (!isset($repository[$file])) {
            $file_path = $configPath . DIRECTORY_SEPARATOR . $file . '.php';
            if (file_exists($file_path)) {
                $repository[$file] = include $file_path;
            } else {
                return $default; // File tidak ada
            }
        }

        if (empty($parts)) {
            return $repository[$file];
        }

        $array = $repository[$file];
        foreach ($parts as $part) {
            if (is_array($array) && array_key_exists($part, $array)) {
                $array = $array[$part];
            } else {
                return $default; // Key tidak ada di dalam file
            }
        }

        return $array;
    }
}

if (!function_exists('cache')) {
    function cache(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return \Novalites\Cache\Cache::class; // return manager, biar bisa cache()::remember(...)
        }
        return \Novalites\Cache\Cache::get($key, $default);
    }
}


if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = __DIR__ . '/../storage';
        return $path ? "{$base}/" . ltrim($path, '/') : $base;
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): Response
    {
        return Response::view($view, $data);
    }
}

if (!function_exists('auth')) {
    function auth(): string
    {
        return \Novalites\Auth\Auth::class;
    }
}

if (!function_exists('session')) {
    function session(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return \Novalites\Session\Session::class;
        }
        return \Novalites\Session\Session::get($key, $default);
    }
}

if (!function_exists('str')) {
    function str(?string $value = null): mixed
    {
        if ($value === null) {
            return \Novalites\Support\Str::class;
        }
        return new \Novalites\Support\StringableWrapper($value);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('jtech_env')) {
    function jtech_env(string $key, ?string $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        return $default;
    }
}


if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        return CsrfToken::get();
    }
}

if (!function_exists('jtech_app')) {
    function jtech_app()
    {
        return Container::getInstance();
    }
}
