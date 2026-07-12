<?php

namespace Novalites\Support;

class StringableWrapper
{
    public function __construct(protected string $value) {}

    public function __call(string $method, array $args): mixed
    {
        $result = Str::{$method}($this->value, ...$args);

        // Kalau hasilnya string, wrap lagi biar bisa di-chain terus
        return is_string($result) ? new self($result) : $result;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }
}
