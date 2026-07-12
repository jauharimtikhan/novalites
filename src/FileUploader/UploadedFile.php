<?php

namespace Novalites\FileUploader;

use Novalites\Exception\FileUploadException;

class UploadedFile
{
    public function __construct(
        protected string $tmpName,
        protected string $originalName,
        protected string $mimeType,
        protected int $size,
        protected int $error
    ) {}

    public static function fromArray(array $file): self
    {
        return new self(
            $file['tmp_name'] ?? '',
            $file['name'] ?? '',
            $file['type'] ?? '',
            $file['size'] ?? 0,
            $file['error'] ?? UPLOAD_ERR_NO_FILE
        );
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->tmpName);
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getErrorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK         => 'Tidak ada error.',
            UPLOAD_ERR_INI_SIZE   => 'File melebihi batas upload_max_filesize di php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'File melebihi batas MAX_FILE_SIZE di form.',
            UPLOAD_ERR_PARTIAL    => 'File cuma ke-upload sebagian.',
            UPLOAD_ERR_NO_FILE    => 'Ga ada file yang di-upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary ga ada.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal nulis file ke disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh PHP extension.',
            default                => 'Error upload ga diketahui.',
        };
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getFilenameWithoutExtension(): string
    {
        return pathinfo($this->originalName, PATHINFO_FILENAME);
    }

    public function getExtension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function getMimeType(): string
    {
        // Cek MIME asli dari isi file, bukan cuma header dari client (yang bisa dipalsuin)
        if ($this->isValid() && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $realMime = finfo_file($finfo, $this->tmpName);
            finfo_close($finfo);
            return $realMime ?: $this->mimeType;
        }
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getSizeInKb(): float
    {
        return round($this->size / 1024, 2);
    }

    public function getSizeInMb(): float
    {
        return round($this->size / 1024 / 1024, 2);
    }

    public function getTmpName(): string
    {
        return $this->tmpName;
    }

    /**
     * Generate nama file unik, biar ga ketiban file lain.
     */
    public function generateUniqueName(): string
    {
        $ext = $this->getExtension();
        $unique = bin2hex(random_bytes(16));
        return $ext ? "{$unique}.{$ext}" : $unique;
    }

    /**
     * Pindahin file dari tmp ke lokasi tujuan.
     */
    public function moveTo(string $destinationPath): bool
    {
        if (!$this->isValid()) {
            throw new FileUploadException($this->getErrorMessage());
        }

        $dir = dirname($destinationPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return move_uploaded_file($this->tmpName, $destinationPath);
    }
}
