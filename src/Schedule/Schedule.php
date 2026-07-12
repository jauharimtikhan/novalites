<?php

namespace Novalites\Schedule;

use Closure;

class Schedule
{
    protected array $events = [];

    public function call(Closure $callback, array $parameters = []): Event
    {
        $event = new Event($callback, $parameters);
        $this->events[] = $event;
        return $event;
    }

    /**
     * Jalankan method di class tertentu, format 'ClassName@method'
     */
    public function job(string $classAtMethod, array $parameters = []): Event
    {
        $event = new Event($classAtMethod, $parameters);
        $this->events[] = $event;
        return $event;
    }

    /**
     * Jalankan shell command.
     */
    public function exec(string $command): Event
    {
        $event = new Event(function () use ($command) {
            passthru($command);
        });
        $this->events[] = $event;
        return $event;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Jalankan semua event yang "due" saat ini.
     */
    public function run(): void
    {
        $dueCount = 0;

        foreach ($this->events as $event) {
            if ($event->isDue()) {
                $event->run();
                $dueCount++;
            }
        }

        if ($dueCount === 0) {
            echo "Ga ada schedule yang due saat ini.\n";
        }
    }
}
