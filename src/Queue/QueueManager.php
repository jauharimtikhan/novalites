<?php

namespace Novalites\Queue;

use Novalites\Database\Manager;

class QueueManager
{
    protected static ?QueueManager $instance = null;

    protected string $driver;

    public function __construct(string $driver = 'database')
    {
        $this->driver = $driver;
    }

    public static function getInstance(): static
    {
        if (self::$instance === null) {
            $driver = jtech_env('QUEUE_DRIVER') ?? 'database';
            self::$instance = new static($driver);
        }
        return self::$instance;
    }

    /**
     * Push job ke queue.
     */
    public function push(ShouldQueue $job, string $queue = 'default', int $delay = 0): void
    {
        if ($this->driver === 'sync') {
            // Sync driver: langsung eksekusi, ga masuk tabel jobs
            $job->handle();
            return;
        }

        Manager::table('jobs')->insert([
            'queue'        => $queue,
            'payload'      => $this->serialize($job),
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => time() + $delay,
            'created_at'   => time(),
        ]);
    }

    protected function serialize(ShouldQueue $job): string
    {
        return json_encode([
            'class' => get_class($job),
            'data'  => base64_encode(serialize($job)),
        ]);
    }

    public function unserialize(string $payload): ShouldQueue
    {
        $decoded = json_decode($payload, true);
        return unserialize(base64_decode($decoded['data']));
    }
}
