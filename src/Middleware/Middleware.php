<?php

namespace Novalites\Middleware;


class Middleware
{
    /**
     * Middleware yang jalan di SEMUA request, urutan sesuai array.
     */
    protected array $global = [];

    /**
     * Alias pendek -> class lengkap. Contoh: 'auth' => AuthMiddleware::class
     */
    protected array $aliases = [];

    /**
     * Group middleware, contoh: 'api' => ['throttle', 'auth']
     */
    protected array $groups = [];

    // ---------- Fluent builder, dipanggil dari Application::withMiddleware() ----------

    public function append(string|array $middleware): static
    {
        $this->global = array_merge($this->global, (array) $middleware);
        return $this;
    }

    public function prepend(string|array $middleware): static
    {
        $this->global = array_merge((array) $middleware, $this->global);
        return $this;
    }

    public function alias(array $aliases): static
    {
        $this->aliases = array_merge($this->aliases, $aliases);
        return $this;
    }

    public function group(string $name, array $middleware): static
    {
        $this->groups[$name] = $middleware;
        return $this;
    }

    public function remove(string $middleware): static
    {
        $this->global = array_values(array_filter(
            $this->global,
            fn($m) => $m !== $middleware
        ));
        return $this;
    }

    // ---------- Resolver, dipakai RouteResolver ----------

    public function getGlobal(): array
    {
        return $this->global;
    }

    /**
     * Resolve nama middleware (alias/group/class langsung) jadi array class name.
     */
    public function resolve(string $name): array
    {
        if (isset($this->groups[$name])) {
            $resolved = [];
            foreach ($this->groups[$name] as $item) {
                $resolved = array_merge($resolved, $this->resolve($item));
            }
            return $resolved;
        }

        return [$this->aliases[$name] ?? $name];
    }

    /**
     * Resolve array middleware route (bisa campuran alias/group/class) jadi flat array class name.
     */
    public function resolveMany(array $middlewares): array
    {
        $resolved = [];
        foreach ($middlewares as $middleware) {
            $resolved = array_merge($resolved, $this->resolve($middleware));
        }
        return array_unique($resolved);
    }
}
