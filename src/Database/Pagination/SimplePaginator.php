<?php

namespace Novalites\Database\Pagination;

use JsonSerializable;

class SimplePaginator implements JsonSerializable
{
    protected array $items;
    protected int $perPage;
    protected int $currentPage;
    protected bool $hasMorePages;

    public function __construct(array $items, int $perPage, int $currentPage, bool $hasMorePages)
    {
        $this->items = $items;
        $this->perPage = $perPage;
        $this->currentPage = max($currentPage, 1);
        $this->hasMorePages = $hasMorePages;
    }

    public function items(): array
    {
        return $this->items;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function hasMorePages(): bool
    {
        return $this->hasMorePages;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function through(callable $callback): static
    {
        $this->items = array_map($callback, $this->items);
        return $this;
    }

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
                'current_page'   => $this->currentPage,
                'per_page'       => $this->perPage,
                'has_more_pages' => $this->hasMorePages,
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
}
