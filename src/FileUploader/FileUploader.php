<?php

namespace Novalites\FileUploader;

use Novalites\Exception\FileUploadException;

class FileUploader
{
    protected string $basePath;
    protected string $baseUrl;
    protected array $allowedMimes = [];
    protected array $allowedExtensions = [];
    protected int $maxSizeKb = 5120; // default 5MB
    protected bool $useUniqueName = true;

    public function __construct(?string $basePath = null, ?string $baseUrl = null)
    {
        $this->basePath = $basePath ?? constant('BASE_PATH') . '/storage/uploads';
        $this->baseUrl = $baseUrl ?? app_url('storage/uploads');
    }

    // ---------- Konfigurasi (fluent) ----------

    public function onlyMimes(array $mimes): static
    {
        $this->allowedMimes = $mimes;
        return $this;
    }

    public function onlyExtensions(array $extensions): static
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    public function maxSize(int $kb): static
    {
        $this->maxSizeKb = $kb;
        return $this;
    }

    public function keepOriginalName(bool $keep = true): static
    {
        $this->useUniqueName = !$keep;
        return $this;
    }

    // ---------- Preset umum, biar gampang dipanggil ----------

    public static function forImages(): static
    {
        return (new static())
            ->onlyMimes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->onlyExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp'])
            ->maxSize(2048); // 2MB
    }

    public static function forDocuments(): static
    {
        return (new static())
            ->onlyMimes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
            ->onlyExtensions(['pdf', 'doc', 'docx'])
            ->maxSize(10240); // 10MB
    }

    // ---------- Validasi ----------

    protected function validate(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new FileUploadException($file->getErrorMessage());
        }

        if ($this->maxSizeKb > 0 && $file->getSizeInKb() > $this->maxSizeKb) {
            throw new FileUploadException(
                "Ukuran file ({$file->getSizeInKb()} KB) melebihi batas maksimal ({$this->maxSizeKb} KB)."
            );
        }

        if (!empty($this->allowedExtensions) && !in_array($file->getExtension(), $this->allowedExtensions, true)) {
            $allowed = implode(', ', $this->allowedExtensions);
            throw new FileUploadException("Ekstensi file '.{$file->getExtension()}' tidak diizinkan. Yang diizinkan: {$allowed}");
        }

        if (!empty($this->allowedMimes) && !in_array($file->getMimeType(), $this->allowedMimes, true)) {
            throw new FileUploadException("Tipe file '{$file->getMimeType()}' tidak diizinkan.");
        }
    }

    // ---------- Simpan file ----------

    /**
     * Simpan satu file, return path relatif & URL-nya.
     */
    public function store(UploadedFile $file, string $subDirectory = ''): array
    {
        $this->validate($file);

        $filename = $this->useUniqueName
            ? $file->generateUniqueName()
            : $this->sanitizeFilename($file->getOriginalName());

        $subDirectory = trim($subDirectory, '/');
        $relativePath = $subDirectory ? "{$subDirectory}/{$filename}" : $filename;
        $fullPath = rtrim($this->basePath, '/') . '/' . $relativePath;

        if (!$file->moveTo($fullPath)) {
            throw new FileUploadException('Gagal menyimpan file ke server.');
        }

        return [
            'original_name' => $file->getOriginalName(),
            'filename'       => $filename,
            'path'           => $relativePath,
            'full_path'      => $fullPath,
            'url'            => rtrim($this->baseUrl, '/') . '/' . $relativePath,
            'mime_type'      => $file->getMimeType(),
            'size'           => $file->getSize(),
            'size_kb'        => $file->getSizeInKb(),
        ];
    }

    /**
     * Simpan banyak file sekaligus (misal input type="file" multiple).
     */
    public function storeMany(array $files, string $subDirectory = ''): array
    {
        $results = [];
        foreach ($files as $file) {
            $results[] = $this->store($file, $subDirectory);
        }
        return $results;
    }

    /**
     * Hapus file yang udah tersimpan.
     */
    public function delete(string $relativePath): bool
    {
        $fullPath = rtrim($this->basePath, '/') . '/' . ltrim($relativePath, '/');

        if (!file_exists($fullPath)) {
            return false;
        }

        return unlink($fullPath);
    }

    protected function sanitizeFilename(string $filename): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Buang karakter aneh, ganti spasi jadi underscore
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
        $name = trim($name, '_');

        // Tambahin timestamp biar ga bentrok kalau nama sama
        $name .= '_' . time();

        return $ext ? "{$name}.{$ext}" : $name;
    }
}
