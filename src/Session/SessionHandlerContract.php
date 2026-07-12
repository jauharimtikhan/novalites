<?php

namespace Novalites\Session;

interface SessionHandlerContract
{
    public function start(): void;
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function forget(string $key): void;
    public function all(): array;
    public function flush(): void;
    public function regenerate(): void;
    public function getId(): string;
    public function save(): void;
}
