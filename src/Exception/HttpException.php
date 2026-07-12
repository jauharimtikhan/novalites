<?php

namespace Novalites\Exception;

class HttpException extends \RuntimeException
{
    public function __construct(
        protected int $statusCode,
        string $message = '',
        array $headers = []
    ) {
        parent::__construct($message ?: $this->defaultMessage($statusCode));
        $this->headers = $headers;
    }

    protected array $headers = [];

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    protected function defaultMessage(int $status): string
    {
        return match ($status) {
            404 => 'Halaman tidak ditemukan.',
            403 => 'Akses ditolak.',
            405 => 'Method tidak diizinkan.',
            422 => 'Data tidak valid.',
            500 => 'Terjadi kesalahan pada server.',
            default => 'Terjadi kesalahan.',
        };
    }
}
