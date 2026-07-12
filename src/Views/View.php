<?php

namespace Novalites\Views;

use Novalites\Exception\ViewException;

class View
{
    protected static string $viewsPath;
    protected static array $sharedData = [];

    // Buat nampung section pas render layout
    protected array $sections = [];
    protected ?string $currentSection = null;
    protected ?string $layout = null;
    protected string $sectionContent = '';

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/');
    }

    /**
     * Data yang otomatis ke-share ke SEMUA view (misal auth user, app name, dll)
     */
    public static function share(string $key, mixed $value): void
    {
        self::$sharedData[$key] = $value;
    }

    /**
     * Render view, return string HTML.
     */
    public static function make(string $view, array $data = []): string
    {
        $instance = new self();
        return $instance->render($view, $data);
    }

    public function render(string $view, array $data = []): string
    {
        $path = $this->resolvePath($view);

        if (!file_exists($path)) {
            throw new ViewException("View [{$view}] tidak ditemukan di: {$path}");
        }

        $data = array_merge(self::$sharedData, $data);

        // Render isi view dulu (bisa jadi manggil $this->extends() di dalamnya)
        $content = $this->evaluate($path, $data);

        // Kalau view manggil extends(), render layout-nya dengan section yang udah dikumpulin
        if ($this->layout !== null) {
            $layoutPath = $this->resolvePath($this->layout);

            if (!file_exists($layoutPath)) {
                throw new ViewException("Layout [{$this->layout}] tidak ditemukan.");
            }

            // 'content' section otomatis diisi hasil render view utama
            $this->sections['content'] = $content;

            return $this->evaluate($layoutPath, $data);
        }

        return $content;
    }

    protected function evaluate(string $path, array $data): string
    {
        extract($data);

        ob_start();
        include $path;
        return ob_get_clean();
    }

    protected function resolvePath(string $view): string
    {
        $view = str_replace('.', '/', $view);
        return self::$viewsPath . "/{$view}.php";
    }

    // ---------- Layout & Section (dipanggil DARI DALAM file view) ----------

    protected function extends(string $layout): void
    {
        $this->layout = $layout;
    }

    protected function section(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    protected function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new ViewException('endSection() dipanggil tanpa section() sebelumnya.');
        }

        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = null;
    }

    protected function yield(string $name, string $default = ''): void
    {
        echo $this->sections[$name] ?? $default;
    }

    protected function include(string $view, array $data = []): void
    {
        $path = $this->resolvePath($view);

        if (!file_exists($path)) {
            throw new ViewException("Partial [{$view}] tidak ditemukan.");
        }

        echo $this->evaluate($path, array_merge(self::$sharedData, $data));
    }

    // ---------- Helper escaping (WAJIB dipakai buat output data dinamis) ----------

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
