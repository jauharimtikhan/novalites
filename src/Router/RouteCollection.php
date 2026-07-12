<?php

namespace Novalites\Router;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

class RouteCollection implements IteratorAggregate, Countable
{
    /**
     * Registered routes.
     */
    public array $routes = [];

    /**
     * Add new route.
     */
    public function add(array $route): int
    {
        $this->routes[] = $route;

        return array_key_last($this->routes);
    }

    /**
     * Get all registered routes.
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Get route by index.
     */
    public function get(int $index): ?array
    {
        return $this->routes[$index] ?? null;
    }

    /**
     * Get last registered route.
     */
    public function last(): ?array
    {
        if (empty($this->routes)) {
            return null;
        }

        return $this->routes[array_key_last($this->routes)];
    }

    /**
     * Replace route.
     */
    public function replace(int $index, array $route): void
    {
        if (!isset($this->routes[$index])) {
            return;
        }

        $this->routes[$index] = $route;
    }

    /**
     * Replace last registered route.
     */
    public function replaceLast(array $route): void
    {
        if (empty($this->routes)) {
            return;
        }

        $this->routes[array_key_last($this->routes)] = $route;
    }

    /**
     * Remove route.
     */
    public function remove(int $index): void
    {
        unset($this->routes[$index]);

        $this->routes = array_values($this->routes);
    }

    /**
     * Remove all routes.
     */
    public function clear(): void
    {
        $this->routes = [];
    }

    /**
     * Check route exists.
     */
    public function has(int $index): bool
    {
        return isset($this->routes[$index]);
    }

    /**
     * Total registered routes.
     */
    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Allow foreach().
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->routes);
    }
}
