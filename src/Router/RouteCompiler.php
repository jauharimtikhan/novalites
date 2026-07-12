<?php

namespace Novalites\Router;

class RouteCompiler
{
    /**
     * Active route group stack.
     */
    protected array $groupStack = [];

    /**
     * Push group attributes.
     */
    public function pushGroup(array $attributes): void
    {
        $this->groupStack[] = $attributes;
    }

    /**
     * Pop last group.
     */
    public function popGroup(): void
    {
        array_pop($this->groupStack);
    }

    /**
     * Get current group stack.
     */
    public function getGroupStack(): array
    {
        return $this->groupStack;
    }

    /**
     * Compile route.
     */
    public function compile(array $route): array
    {
        foreach ($this->groupStack as $group) {

            $route = $this->merge($route, $group);
        }

        return $route;
    }

    /**
     * Merge group attributes.
     */
    protected function merge(array $route, array $group): array
    {
        /*
        |--------------------------------------------------------------------------
        | Prefix
        |--------------------------------------------------------------------------
        */

        if (!empty($group['prefix'])) {

            $route['path'] = $this->joinPath(
                $group['prefix'],
                $route['path']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Middleware
        |--------------------------------------------------------------------------
        */

        if (!empty($group['middleware'])) {

            $route['middleware'] = array_merge(
                $group['middleware'],
                $route['middleware'] ?? []
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Name Prefix
        |--------------------------------------------------------------------------
        */

        if (!empty($group['as'])) {

            $route['name'] = ($group['as'])
                . ($route['name'] ?? '');
        }

        /*
        |--------------------------------------------------------------------------
        | Namespace
        |--------------------------------------------------------------------------
        */

        if (
            !empty($group['namespace']) &&
            is_array($route['action'])
        ) {

            $controller = ltrim($route['action'][0], '\\');

            $route['action'][0] =
                trim($group['namespace'], '\\')
                . '\\'
                . $controller;
        }

        /*
        |--------------------------------------------------------------------------
        | Controller Group
        |--------------------------------------------------------------------------
        */

        if (
            !empty($group['controller']) &&
            is_string($route['action'])
        ) {

            $route['action'] = [
                $group['controller'],
                $route['action']
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Domain
        |--------------------------------------------------------------------------
        */

        if (!empty($group['domain'])) {

            $route['domain'] = $group['domain'];
        }

        /*
        |--------------------------------------------------------------------------
        | Where
        |--------------------------------------------------------------------------
        */

        if (!empty($group['where'])) {

            $route['where'] = array_merge(
                $group['where'],
                $route['where'] ?? []
            );
        }

        return $route;
    }

    /**
     * Join path.
     */
    protected function joinPath(
        string $prefix,
        string $path
    ): string {

        return '/'
            . trim($prefix, '/')
            . '/'
            . trim($path, '/');
    }
}
