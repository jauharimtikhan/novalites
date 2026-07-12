<?php

namespace Novalites\Container;

use Novalites\Container\Container;

class ContextualBindingBuilder
{
    protected string $needs;

    public function __construct(
        protected Container $container,
        protected string $concrete
    ) {}

    public function needs(string $abstract): static
    {
        $this->needs = $abstract;
        return $this;
    }

    public function give(\Closure|string $implementation): void
    {
        $this->container->addContextualBinding($this->concrete, $this->needs, $implementation);
    }
}
