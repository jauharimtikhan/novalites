<?php

namespace Novalites\Database\Pagination;

use JsonSerializable;

class Paginator implements JsonSerializable
{
    protected array $items;
    protected int $total;
    protected int $perPage;
    protected int $currentPage;
    protected int $lastPage;
    protected string $pageName;

    public function __construct(
        array $items,
        int $total,
        int $perPage,
        int $currentPage,
        string $pageName = 'page'
    ) {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = max($perPage, 1);
        $this->currentPage = max($currentPage, 1);
        $this->lastPage = max((int) ceil($total / $this->perPage), 1);
        $this->pageName = $pageName;
    }

    // ── ACCESSORS ──────────────────────────────────────────

    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function firstItem(): ?int
    {
        return $this->total === 0 ? null : ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function lastItem(): ?int
    {
        if ($this->total === 0) {
            return null;
        }
        return min($this->firstItem() + $this->perPage - 1, $this->total);
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function count(): int
    {
        return count($this->items);
    }

    // ── TRANSFORM ───────────────────────────────────────────

    /**
     * Transform tiap item di dalam page, berguna buat convert Model -> array
     * atau nge-map ke DTO/Resource sebelum di-return sebagai response.
     */
    public function through(callable $callback): static
    {
        $this->items = array_map($callback, $this->items);
        return $this;
    }

    // ── OUTPUT ────────────────────────────────────────────────

    public function toArray(): array
    {
        return [
            'data' => array_map(
                fn($item) => is_object($item) && method_exists($item, 'toArray')
                    ? $item->toArray()
                    : $item,
                $this->items
            ),
            'meta' => [
                'current_page' => $this->currentPage,
                'per_page'     => $this->perPage,
                'total'        => $this->total,
                'last_page'    => $this->lastPage,
                'from'         => $this->firstItem(),
                'to'           => $this->lastItem(),
                'has_more_pages' => $this->hasMorePages(),
            ],
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // ── HELPER: resolve halaman aktif dari query string ────────

    public static function resolveCurrentPage(string $pageName = 'page'): int
    {
        $page = $_GET[$pageName] ?? 1;
        $page = (int) $page;
        return $page < 1 ? 1 : $page;
    }
}
