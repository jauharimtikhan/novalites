<?php

namespace Novalites\Queue;

class PendingDispatch
{
    protected string $queue = 'default';
    protected int $delay = 0;

    public function __construct(
        protected ShouldQueue $job
    ) {
        // Auto dispatch pas object ini di-destroy (kayak Laravel),
        // TAPI kita bikin eksplisit lewat destructor biar ga kejadian pas exception.
        register_shutdown_function([$this, 'ensureDispatched']);
    }

    protected bool $dispatched = false;

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function delay(int $seconds): static
    {
        $this->delay = $seconds;
        return $this;
    }

    public function ensureDispatched(): void
    {
        if ($this->dispatched) {
            return;
        }
        $this->dispatched = true;

        QueueManager::getInstance()->push($this->job, $this->queue, $this->delay);
    }
}
