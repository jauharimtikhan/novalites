<?php

namespace Novalites\Router;

class PendingRoute
{
    public function __construct(
        protected RouteCollection $routes,
        protected int $index
    ) {}

    /**
     * Get current route.
     */
    protected function route(): array
    {
        return $this->routes->get($this->index);
    }

    /**
     * Save current route.
     */
    protected function save(array $route): static
    {
        $this->routes->replace($this->index, $route);

        return $this;
    }

    /**
     * Set route name.
     */
    public function name(string $name): static
    {
        $route = $this->route();

        $route['name'] = $name;

        return $this->save($route);
    }

    /**
     * Add middleware.
     */
    public function middleware(string|array $middleware): static
    {
        $route = $this->route();

        $route['middleware'] = array_merge(
            $route['middleware'] ?? [],
            (array) $middleware
        );

        return $this->save($route);
    }

    /**
     * Remove middleware.
     */
    public function withoutMiddleware(string|array $middleware): static
    {
        $route = $this->route();

        $remove = (array) $middleware;

        $route['middleware'] = array_values(array_filter(
            $route['middleware'] ?? [],
            fn($item) => !in_array($item, $remove, true)
        ));

        return $this->save($route);
    }

    /**
     * Add route constraints.
     */
    public function where(string|array $name, ?string $pattern = null): static
    {
        $route = $this->route();

        $route['where'] ??= [];

        if (is_array($name)) {
            $route['where'] = array_merge(
                $route['where'],
                $name
            );
        } else {
            $route['where'][$name] = $pattern;
        }

        return $this->save($route);
    }

    /**
     * Shortcut numeric constraint.
     */
    public function whereNumber(string $parameter): static
    {
        return $this->where(
            $parameter,
            '[0-9]+'
        );
    }

    /**
     * Shortcut alpha constraint.
     */
    public function whereAlpha(string $parameter): static
    {
        return $this->where(
            $parameter,
            '[A-Za-z]+'
        );
    }

    /**
     * Shortcut alpha numeric constraint.
     */
    public function whereAlphaNumeric(string $parameter): static
    {
        return $this->where(
            $parameter,
            '[A-Za-z0-9]+'
        );
    }

    /**
     * Shortcut UUID constraint.
     */
    public function whereUuid(string $parameter): static
    {
        return $this->where(
            $parameter,
            '[0-9a-fA-F\-]{36}'
        );
    }

    /**
     * Shortcut slug constraint.
     */
    public function whereSlug(string $parameter): static
    {
        return $this->where(
            $parameter,
            '[a-z0-9\-]+'
        );
    }
}
