<?php

namespace Novalites\Queue;

trait Dispatchable
{
    public static function dispatch(mixed ...$args): PendingDispatch
    {
        return new PendingDispatch(new static(...$args));
    }

    public static function dispatchSync(mixed ...$args): mixed
    {
        $job = new static(...$args);
        return $job->handle();
    }
}
