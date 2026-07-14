<?php

namespace Novalites\Support;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use JsonSerializable;

class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    protected array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    // ── OUTPUT ────────────────────────────────────────────

    public function toArray(): array
    {
        return array_map(
            fn($item) => is_object($item) && method_exists($item, 'toArray') ? $item->toArray() : $item,
            $this->items
        );
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function all(): array
    {
        return $this->items;
    }

    // ── TRANSFORM ───────────────────────────────────────────

    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    public function filter(?callable $callback = null): static
    {
        $filtered = $callback ? array_filter($this->items, $callback) : array_filter($this->items);
        return new static(array_values($filtered));
    }

    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }
        return $this;
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function pluck(string $column, ?string $key = null): static
    {
        $result = [];
        foreach ($this->items as $item) {
            $value = is_array($item) ? $item[$column] : $item->$column;

            if ($key !== null) {
                $keyValue = is_array($item) ? $item[$key] : $item->$key;
                $result[$keyValue] = $value;
            } else {
                $result[] = $value;
            }
        }
        return new static($result);
    }

    // ── ACCESSORS ─────────────────────────────────────────

    public function first(): mixed
    {
        return $this->items[array_key_first($this->items)] ?? null;
    }

    public function last(): mixed
    {
        return $this->items[array_key_last($this->items)] ?? null;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function contains(mixed $value): bool
    {
        return in_array($value, $this->items, true);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function sum(?string $column = null): int|float
    {
        if ($column === null) {
            return array_sum($this->items);
        }
        return array_sum(array_map(fn($item) => is_array($item) ? $item[$column] : $item->$column, $this->items));
    }

    // ── ARRAYACCESS ───────────────────────────────────────

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    // ── ITERATOR ──────────────────────────────────────────

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }
}
