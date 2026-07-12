<?php

namespace Novalites\Http;

use JsonException;

class Response
{
    protected mixed $content;
    protected int $statusCode = 200;
    protected array $headers = [];

    public function __construct(mixed $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    // ---------- Method yang LANGSUNG kirim & exit (dipanggil sebagai statement) ----------

    /**
     * Mengirim response JSON.
     */
    public static function json(
        mixed $data = null,
        int $status = 200,
        array $headers = [],
        int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ): never {

        try {
            $body = json_encode($data, $options | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $status = 500;
            $body = json_encode([
                'success' => false,
                'message' => 'Gagal mengubah data menjadi JSON.',
            ]);
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Length: ' . strlen($body));

            foreach ($headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $body;
        exit;
    }

    /**
     * Mengirim response text biasa.
     */
    public static function text(
        string $content,
        int $status = 200,
        array $headers = []
    ): never {

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/plain; charset=UTF-8');
            header('Content-Length: ' . strlen($content));

            foreach ($headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $content;
        exit;
    }

    /**
     * Mengirim response HTML (deferred — return instance, dikirim via ->send()).
     */
    public static function html(
        string $content,
        int $status = 200
    ): static {

        return new static($content, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Redirect ke URL tertentu.
     */
    public static function redirect(
        string $url,
        int $status = 302
    ): never {

        // WAJIB: buang \r\n biar ga bisa dipakai buat CRLF/header injection
        // kalau $url berasal dari input user (query param, dll)
        $url = str_replace(["\r", "\n"], '', $url);

        if (!headers_sent()) {
            header("Location: {$url}", true, $status);
        }

        exit;
    }

    /**
     * Mengirim file download.
     */
    public static function download(
        string $path,
        ?string $filename = null
    ): never {

        if (!is_file($path)) {
            self::json([
                'success' => false,
                'message' => 'File tidak ditemukan.'
            ], 404);
        }

        $filename ??= basename($path);
        // Cegah header injection lewat nama file (misal hasil upload user)
        $filename = str_replace(["\r", "\n", '"'], '', $filename);

        if (!headers_sent()) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: no-cache, must-revalidate');
        }

        readfile($path);
        exit;
    }

    /**
     * Menampilkan file asset (gambar, css, js, dll) di browser dari symlink/storage.
     */
    public static function serveAsset(
        string $path,
        array $headers = []
    ): never {

        if (!is_file($path)) {
            self::json([
                'success' => false,
                'message' => 'Asset tidak ditemukan.'
            ], 404);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'pdf'   => 'application/pdf',
            'mp4'   => 'video/mp4',
            'json'  => 'application/json',
            'txt'   => 'text/plain',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
        ];

        $contentType = $mimeTypes[$extension] ?? (mime_content_type($path) ?: 'application/octet-stream');

        if (!headers_sent()) {
            http_response_code(200);
            header("Content-Type: {$contentType}");
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: public, max-age=86400');

            foreach ($headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        readfile($path);
        exit;
    }

    /**
     * Response error terstandarisasi.
     */
    public static function error(string $message, int $code = 400, array $errors = []): never
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }

    /**
     * Response success terstandarisasi.
     */
    public static function success(mixed $data = null, string $message = '', int $code = 200): never
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // ---------- Method deferred — return instance, dikirim via ->send() ----------

    public static function view(string $view, array $data = [], int $status = 200): static
    {
        $html = \Novalites\Templating\TemplateEngine::make($view, $data);
        return static::html($html, $status);
    }

    public function status(int $code): static
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function send(): never
    {
        // Bersihkan SEMUA level output buffer yang mungkin nyangkut,
        // bukan cuma satu level (fix dari versi lama yang cuma ob_clean() sekali)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $key => $value) {
                header("{$key}: {$value}");
            }
        }

        if (is_array($this->content) || is_object($this->content)) {
            echo json_encode($this->content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo (string) $this->content;
        }

        exit;
    }
}
