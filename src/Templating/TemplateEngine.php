<?php

namespace Novalites\Templating;

use Novalites\Exception\TemplateException;

class TemplateEngine
{
    protected static string $viewsPath;
    protected static string $cachePath;
    protected static array $sharedData = [];
    protected static ?TemplateCompiler $compiler = null;

    protected array $sections = [];
    protected array $sectionStack = [];
    protected array $stacks = [];
    protected array $pushStack = [];
    protected ?string $layout = null;

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/');
    }

    public static function getCachePath()
    {
        return self::$cachePath;
    }

    public static function setCachePath(string $path): void
    {
        self::$cachePath = rtrim($path, '/');

        if (!is_dir(self::$cachePath)) {
            mkdir(self::$cachePath, 0755, true);
        }
    }

    public static function share(string $key, mixed $value): void
    {
        self::$sharedData[$key] = $value;
    }

    protected static function compiler(): TemplateCompiler
    {
        return self::$compiler ??= new TemplateCompiler();
    }

    public static function make(string $view, array $data = []): string
    {
        $instance = new self();
        return $instance->render($view, $data);
    }

    public function render(string $view, array $data = []): string
    {
        $sourcePath = $this->resolveSourcePath($view);

        if (!file_exists($sourcePath)) {
            throw new TemplateException("View [{$view}] tidak ditemukan di: {$sourcePath}");
        }

        $compiledPath = $this->getCompiledPath($view);

        if ($this->isExpired($sourcePath, $compiledPath)) {
            $this->compileToFile($sourcePath, $compiledPath);
        }

        $data = array_merge(self::$sharedData, $data);

        $content = $this->evaluate($compiledPath, $data);

        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null; // ✅ WAJIB reset dulu, biar layout.tpl ga nganggep dirinya extends lagi

            return $this->render($layout, $data);
        }

        return $content;
    }

    /**
     * Cek apakah file compiled cache udah expired (source lebih baru dari compiled, atau belum ada sama sekali).
     */
    protected function isExpired(string $sourcePath, string $compiledPath): bool
    {
        if (!file_exists($compiledPath)) {
            return true;
        }

        return filemtime($sourcePath) > filemtime($compiledPath);
    }

    protected function compileToFile(string $sourcePath, string $compiledPath): void
    {
        $raw = file_get_contents($sourcePath);
        $compiled = self::compiler()->compile($raw);

        $dir = dirname($compiledPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($compiledPath, $compiled);
    }

    protected function evaluate(string $compiledPath, array $data): string
    {
        extract($data);

        ob_start();
        include $compiledPath;
        return ob_get_clean();
    }

    protected function resolveSourcePath(string $view): string
    {
        $view = str_replace('.', '/', $view);
        return self::$viewsPath . "/{$view}.jtech.php";
    }

    protected function getCompiledPath(string $view): string
    {
        $hash = md5($view);
        $safeName = str_replace(['.', '/'], '_', $view);
        return self::$cachePath . "/{$safeName}_{$hash}.php";
    }

    // ---------- Method yang dipanggil DARI compiled file (via $this->...) ----------

    protected function extendsLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    protected function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    protected function stopSection(bool $show = false): string
    {
        $name = array_pop($this->sectionStack);

        if ($name === null) {
            throw new TemplateException('endsection dipanggil tanpa section sebelumnya.');
        }

        $this->sections[$name] = ob_get_clean();

        if ($show) {
            return $this->sections[$name];
        }

        return '';
    }

    protected function startPush(string $name): void
    {
        $this->pushStack[] = $name;
        ob_start();
    }

    protected function stopPush(): void
    {
        $name = array_pop($this->pushStack);

        if ($name === null) {
            throw new TemplateException('endpush dipanggil tanpa push sebelumnya.');
        }

        $content = ob_get_clean();

        $this->stacks[$name] ??= [];
        $this->stacks[$name][] = $content;
    }

    /**
     * Sama kayak stopPush(), tapi taruh di AWAL stack, bukan di akhir.
     * Berguna kalau ada script yang wajib load duluan (misal jQuery sebelum plugin lain).
     */
    protected function startPrepend(string $name): void
    {
        $this->pushStack[] = $name;
        ob_start();
    }

    protected function stopPrepend(): void
    {
        $name = array_pop($this->pushStack);

        if ($name === null) {
            throw new TemplateException('endprepend dipanggil tanpa prepend sebelumnya.');
        }

        $content = ob_get_clean();

        $this->stacks[$name] ??= [];
        array_unshift($this->stacks[$name], $content);
    }

    protected function yieldStack(string $name): string
    {
        if (!isset($this->stacks[$name])) {
            return '';
        }

        return implode("\n", $this->stacks[$name]);
    }

    protected function section(string $name, string $value): void
    {
        $this->sections[$name] = $value;
    }

    protected function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    protected function includeView(string $view, array $data = []): string
    {
        return $this->render($view, array_merge(self::$sharedData, $data));
    }

    // ---------- Cache management (dipanggil dari Kernel command) ----------

    public static function clearCache(): int
    {
        if (!is_dir(self::$cachePath)) {
            return 0;
        }

        $files = glob(self::$cachePath . '/*.php');
        $count = 0;

        foreach ($files as $file) {
            unlink($file);
            $count++;
        }

        return $count;
    }
}
