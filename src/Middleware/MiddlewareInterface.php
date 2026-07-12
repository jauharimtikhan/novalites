<?php

namespace Novalites\Middleware;

use Novalites\Http\Request;

interface MiddlewareInterface
{
    public function handle(Request $request): void;
}
