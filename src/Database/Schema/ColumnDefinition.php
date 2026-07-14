<?php

namespace Novalites\Database\Schema;

class ColumnDefinition
{
    public string $name;
    public string $type;
    public array $parameters = [];
    public bool $nullable = false;
    public mixed $default = null;
    public bool $hasDefault = false;
    public bool $unsigned = false;
    public bool $autoIncrement = false;
    public ?string $after = null;
    public bool $unique = false;
    public bool $index = false;
    public ?string $comment = null;

    public function __construct(string $name, string $type, array $parameters = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->parameters = $parameters;
    }

    public function nullable(bool $value = true): static
    {
        $this->nullable = $value;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function unsigned(): static
    {
        $this->unsigned = true;
        return $this;
    }

    public function autoIncrement(): static
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function after(string $column): static
    {
        $this->after = $column;
        return $this;
    }

    public function unique(): static
    {
        $this->unique = true;
        return $this;
    }

    public function index(): static
    {
        $this->index = true;
        return $this;
    }

    public function comment(string $text): static
    {
        $this->comment = $text;
        return $this;
    }
}
