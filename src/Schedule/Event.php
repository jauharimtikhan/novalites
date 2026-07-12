<?php

namespace Novalites\Schedule;

use Closure;

class Event
{
    protected string $expression = '* * * * *'; // default: tiap menit
    protected string $description = '';
    protected bool $withoutOverlapping = false;
    protected ?string $lockFile = null;

    public function __construct(
        protected Closure|string $callback,
        protected array $parameters = []
    ) {}

    // ---------- Frekuensi (fluent, mirip Laravel) ----------

    public function cron(string $expression): static
    {
        $this->expression = $expression;
        return $this;
    }

    public function everyMinute(): static
    {
        return $this->cron('* * * * *');
    }

    public function everyFiveMinutes(): static
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): static
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): static
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): static
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): static
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): static
    {
        return $this->cron("{$minute} * * * *");
    }

    public function daily(): static
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): static
    {
        [$hour, $minute] = explode(':', $time . ':0');
        return $this->cron("{$minute} {$hour} * * *");
    }

    public function weekly(): static
    {
        return $this->cron('0 0 * * 0');
    }

    public function monthly(): static
    {
        return $this->cron('0 0 1 * *');
    }

    public function weekdays(): static
    {
        [$min, $hour,, $month] = explode(' ', $this->expression);
        return $this->cron("{$min} {$hour} * {$month} 1-5");
    }

    public function weekends(): static
    {
        [$min, $hour,, $month] = explode(' ', $this->expression);
        return $this->cron("{$min} {$hour} * {$month} 0,6");
    }

    // ---------- Metadata ----------

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function withoutOverlapping(): static
    {
        $this->withoutOverlapping = true;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description ?: $this->summarize();
    }

    protected function summarize(): string
    {
        if (is_string($this->callback)) {
            return $this->callback;
        }
        return 'Closure';
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    // ---------- Eksekusi ----------

    public function isDue(): bool
    {
        return CronExpression::isDue($this->expression, time());
    }

    public function run(): void
    {
        $lockFile = $this->getLockFile();

        if ($this->withoutOverlapping && file_exists($lockFile)) {
            echo "Skip (masih berjalan): {$this->getDescription()}\n";
            return;
        }

        if ($this->withoutOverlapping) {
            file_put_contents($lockFile, time());
        }

        try {
            echo "Menjalankan: {$this->getDescription()}\n";

            if ($this->callback instanceof Closure) {
                ($this->callback)(...$this->parameters);
            } elseif (is_string($this->callback) && str_contains($this->callback, '@')) {
                [$class, $method] = explode('@', $this->callback, 2);
                (new $class())->{$method}(...$this->parameters);
            } elseif (is_callable($this->callback)) {
                call_user_func_array($this->callback, $this->parameters);
            }

            echo "Selesai: {$this->getDescription()}\n";
        } finally {
            if ($this->withoutOverlapping && file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }

    protected function getLockFile(): string
    {
        if ($this->lockFile === null) {
            $hash = md5($this->getDescription() . $this->expression);
            $this->lockFile = sys_get_temp_dir() . "/jtech_schedule_{$hash}.lock";
        }
        return $this->lockFile;
    }
}
