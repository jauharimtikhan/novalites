<?php

namespace Novalites\Queue;

use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

class Worker
{
    protected bool $shouldStop = false;

    public function __construct()
    {
        // Biar bisa di-stop rapi pakai Ctrl+C / SIGTERM (Linux/Mac)
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->shouldStop = true);
            pcntl_signal(SIGINT, fn() => $this->shouldStop = true);
        }
    }

    public function work(string $queue = 'default', int $sleep = 3, int $maxJobs = 0): void
    {
        $processed = 0;

        echo "Worker jalan, listening queue [{$queue}]...\n";

        while (!$this->shouldStop) {
            $job = $this->getNextJob($queue);

            if ($job === null) {
                sleep($sleep);
                continue;
            }

            $this->process($job);
            $processed++;

            if ($maxJobs > 0 && $processed >= $maxJobs) {
                echo "Mencapai batas {$maxJobs} job, worker berhenti.\n";
                break;
            }
        }

        echo "Worker berhenti.\n";
    }

    /**
     * Proses satu job aja (buat cron-based worker via schedule, bukan long-running).
     */
    public function runOnce(string $queue = 'default'): bool
    {
        $job = $this->getNextJob($queue);

        if ($job === null) {
            return false;
        }

        $this->process($job);
        return true;
    }

    protected function getNextJob(string $queue): ?object
    {
        // Ambil job yang: belum di-reserve, sudah waktunya jalan (available_at <= now)
        $job = Capsule::table('jobs')
            ->where('queue', $queue)
            ->where('available_at', '<=', time())
            ->whereNull('reserved_at')
            ->orderBy('id')
            ->first();

        if ($job === null) {
            return null;
        }

        // Reserve job ini biar worker lain (kalau ada multi-worker) ga rebutan ambil job yang sama
        $updated = Capsule::table('jobs')
            ->where('id', $job->id)
            ->whereNull('reserved_at') // double-check, jaga race condition
            ->update(['reserved_at' => time()]);

        if ($updated === 0) {
            return null; // keduluan worker lain
        }

        return $job;
    }

    protected function process(object $jobRow): void
    {
        $manager = QueueManager::getInstance();

        try {
            $job = $manager->unserialize($jobRow->payload);

            echo "Processing: " . get_class($job) . " (id: {$jobRow->id})\n";

            $job->handle();

            // Sukses -> hapus dari tabel jobs
            Capsule::table('jobs')->where('id', $jobRow->id)->delete();

            echo "Selesai: job #{$jobRow->id}\n";
        } catch (Throwable $e) {
            $this->handleFailure($jobRow, $e);
        }
    }

    protected function handleFailure(object $jobRow, Throwable $e): void
    {
        $attempts = $jobRow->attempts + 1;
        $manager = QueueManager::getInstance();
        $job = $manager->unserialize($jobRow->payload);

        $maxAttempts = $job->maxAttempts ?? 1;

        echo "Job #{$jobRow->id} gagal (percobaan {$attempts}/{$maxAttempts}): " . $e->getMessage() . "\n";

        if ($attempts >= $maxAttempts) {
            // Habis percobaan -> masukin ke failed_jobs, hapus dari jobs
            Capsule::table('failed_jobs')->insert([
                'queue'     => $jobRow->queue,
                'payload'   => $jobRow->payload,
                'exception' => $e->getMessage() . "\n" . $e->getTraceAsString(),
                'failed_at' => date('Y-m-d H:i:s'),
            ]);

            Capsule::table('jobs')->where('id', $jobRow->id)->delete();

            if (method_exists($job, 'failed')) {
                $job->failed($e);
            }
        } else {
            // Masih ada kesempatan retry -> lepas reservasi, naikkan attempts
            // Delay retry sedikit (exponential-ish backoff sederhana)
            $backoff = 10 * $attempts;

            Capsule::table('jobs')->where('id', $jobRow->id)->update([
                'attempts'     => $attempts,
                'reserved_at'  => null,
                'available_at' => time() + $backoff,
            ]);
        }
    }
}
