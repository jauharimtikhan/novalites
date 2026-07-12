<?php

namespace Novalites\Router;

use Closure;

class PendingGroup
{
    /**
     * Group attributes.
     */
    protected array $attributes = [];

    public function __construct(
        protected RouteCompiler $compiler
    ) {}

    /**
     * Prefix group.
     */
    public function prefix(string $prefix): static
    {
        $this->attributes['prefix'] = trim($prefix, '/');

        return $this;
    }

    /**
     * Namespace group.
     */
    public function namespace(string $namespace): static
    {
        $this->attributes['namespace'] = trim($namespace, '\\');

        return $this;
    }

    /**
     * Domain group.
     */
    public function domain(string $domain): static
    {
        $this->attributes['domain'] = $domain;

        return $this;
    }

    /**
     * Controller group.
     */
    public function controller(string $controller): static
    {
        $this->attributes['controller'] = $controller;

        return $this;
    }

    /**
     * Middleware group.
     */
    public function middleware(string|array $middleware): static
    {
        $this->attributes['middleware'] = array_merge(
            $this->attributes['middleware'] ?? [],
            (array) $middleware
        );

        return $this;
    }

    /**
     * Route name prefix.
     */
    public function name(string $name): static
    {
        $this->attributes['as'] = $name;

        return $this;
    }

    /**
     * Route constraints.
     */
    public function where(array|string $key, ?string $value = null): static
    {
        $this->attributes['where'] ??= [];

        if (is_array($key)) {

            $this->attributes['where'] = array_merge(
                $this->attributes['where'],
                $key
            );
        } else {

            $this->attributes['where'][$key] = $value;
        }

        return $this;
    }

    /**
     * Execute group.
     */
    public function group(Closure $callback): void
    {
        $this->compiler->pushGroup($this->attributes);

        try {

            $callback();
        } finally {

            $this->compiler->popGroup();
        }
    }
}
