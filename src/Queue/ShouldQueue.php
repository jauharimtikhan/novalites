<?php

namespace Novalites\Queue;

/**
 * Marker interface — job yang implement ini bisa di-dispatch ke queue.
 */
interface ShouldQueue
{
    public function handle(): void;
}
