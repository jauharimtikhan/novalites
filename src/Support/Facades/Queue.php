<?php

namespace Novalites\Support\Facades;

use Novalites\Support\Facade;
use Novalites\Queue\QueueManager;

/**
 * @method static void push(\Novalites\Queue\ShouldQueue $job, string $queue = 'default', int $delay = 0)
 */
class Queue extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return QueueManager::class;
    }
}
