<?php

namespace Novalites\Console;

use Novalites\Database\Manager;
use Novalites\Router\Route;
use Novalites\Support\DB;

class Console
{
    /**
     * Daftar command yang tersedia.
     * Format: 'nama_command' => [class_method, deskripsi]
     */
    protected array $commands = [
        'dev:serve'      => ['method' => 'devServe', 'description' => 'Jalankan development server bawaan PHP'],
        'make:model'     => ['method' => 'makeModel', 'description' => 'Buat file Eloquent Model baru'],
        'make:controller' => ['method' => 'makeController', 'description' => 'Buat file Controller baru'],
        'make:migration' => ['method' => 'makeMigration', 'description' => 'Buat file migration baru'],
        'migrate'        => ['method' => 'migrate', 'description' => 'Jalankan semua migration yang belum dieksekusi'],
        'migrate:fresh' => ['method' => 'migrateFresh', 'description' => 'Drop semua table dan jalankan migrasi baru'],
        'migrate:rollback' => ['method' => 'migrateRollback', 'description' => 'Rollback migration terakhir'],
        'migrate:status' => ['method' => 'migrateStatus', 'description' => 'Lihat status semua migration'],
        'route:list'     => ['method' => 'routeList', 'description' => 'Tampilkan semua route yang terdaftar'],
        'list'           => ['method' => 'help', 'description' => 'Tampilkan semua command yang tersedia'],
        'help'           => ['method' => 'help', 'description' => 'Tampilkan bantuan penggunaan'],
        'queue:work'    => ['method' => 'queueWork', 'description' => 'Jalankan queue worker'],
        'queue:failed'  => ['method' => 'queueFailed', 'description' => 'Lihat daftar job yang gagal'],
        'queue:retry'   => ['method' => 'queueRetry', 'description' => 'Retry job yang gagal'],
        'queue:flush'   => ['method' => 'queueFlush', 'description' => 'Hapus semua failed jobs'],
        'schedule:run'  => ['method' => 'scheduleRun', 'description' => 'Jalankan schedule yang due (dipanggil cron tiap menit)'],
        'schedule:list' => ['method' => 'scheduleList', 'description' => 'Lihat semua schedule terdaftar'],
        'storage:link' => ['method' => 'storageLink', 'description' => 'Publish folder asset/uploads'],
        'cache:clear'    => ['method' => 'cacheClear', 'description' => 'Hapus semua application cache'],
        'view:clear'     => ['method' => 'viewClear', 'description' => 'Hapus compiled view cache'],
        'config:clear'   => ['method' => 'configClear', 'description' => 'Hapus config cache'],
        'route:clear'    => ['method' => 'routeClear', 'description' => 'Hapus route cache'],
        'session:clear'  => ['method' => 'sessionClear', 'description' => 'Hapus semua session yang tersimpan'],
        'log:clear'      => ['method' => 'logClear', 'description' => 'Hapus isi file log'],
        'optimize:clear' => ['method' => 'optimizeClear', 'description' => 'Hapus semua jenis cache sekaligus'],
        'optimize' => ['method' => 'optimize', 'description' => 'Cache config & route buat production'],
        'install:api' => ['method' => "installApi", "description" => "Buat file migrasi database table personal access token dan file REST API"]
    ];

    public function __construct(
        protected mixed $app = null
    ) {}

    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'list';
        $args = array_slice($argv, 2);

        if (!isset($this->commands[$command])) {
            $this->error("Command '{$command}' tidak ditemukan.");
            $this->suggestSimilar($command);
            echo PHP_EOL;
            $this->help();
            return 1;
        }

        $method = $this->commands[$command]['method'];

        try {
            $this->{$method}($args);
            return 0;
        } catch (\Throwable $e) {
            $this->error("Command gagal dijalankan: " . $e->getMessage());
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                echo $e->getTraceAsString() . PHP_EOL;
            }
            return 1;
        }
    }

    // ---------- Output helpers (biar mirip warna artisan) ----------

    protected function info(string $message): void
    {
        echo "\033[32m{$message}\033[0m" . PHP_EOL; // hijau
    }

    protected function error(string $message): void
    {
        echo "\033[31m{$message}\033[0m" . PHP_EOL; // merah
    }

    protected function warn(string $message): void
    {
        echo "\033[33m{$message}\033[0m" . PHP_EOL; // kuning
    }

    protected function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    protected function suggestSimilar(string $input): void
    {
        $closest = null;
        $shortest = -1;

        foreach (array_keys($this->commands) as $cmd) {
            $lev = levenshtein($input, $cmd);
            if ($shortest < 0 || $lev < $shortest) {
                $closest = $cmd;
                $shortest = $lev;
            }
        }

        if ($closest !== null && $shortest <= 3) {
            $this->warn("Mungkin maksud lo: {$closest}?");
        }
    }

    protected function getOption(array $args, string $name, mixed $default = null): mixed
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen($name) + 3);
            }
        }
        return $default;
    }

    protected function getArgument(array $args, int $index): ?string
    {
        // Ambil argumen non-flag (yang ga diawali --)
        $positional = array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')));
        return $positional[$index] ?? null;
    }

    // ---------- Help / list ----------

    public function help(): void
    {
        $this->line();
        $this->info('Jtech Nova Framework — CLI Tool');
        $this->line('Penggunaan:');
        $this->line('  php jtech <command> [options]');
        $this->line();
        $this->info('Command yang tersedia:');

        $maxLength = max(array_map('strlen', array_keys($this->commands)));

        foreach ($this->commands as $name => $meta) {
            $padded = str_pad($name, $maxLength + 2);
            $this->line("  \033[36m{$padded}\033[0m {$meta['description']}");
        }

        $this->line();
    }

    // ---------- dev:serve ----------

    public function devServe(array $args): void
    {
        $host = $this->getOption($args, 'host', '127.0.0.1');
        $port = $this->getOption($args, 'port', '8000');

        $this->info("Server Novalites Rest Api berjalan di http://{$host}:{$port}");
        $this->line("Tekan Ctrl+C untuk berhenti." . PHP_EOL);

        passthru(sprintf(
            'php -S %s:%s -t public public/index.php',
            escapeshellarg($host),
            escapeshellarg($port)
        ));
    }

    // ---------- make:model ----------

    public function makeModel(array $args): void
    {
        $name = $this->getArgument($args, 0);

        if (!$name) {
            $this->error('Nama model wajib diisi. Contoh: php jtech make:model User');
            return;
        }

        $name = ucfirst($name);
        $path = constant('BASE_PATH') . "/app/Models/{$name}.php";

        if (file_exists($path)) {
            $this->error("Model {$name} sudah ada di {$path}");
            return;
        }

        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';

        $withMigration = in_array('--migration', $args, true) || in_array('-m', $args, true);

        $stub = <<<PHP
        <?php

        namespace App\Models;

        use Novalites\RestApi\Database\Model;

        class {$name} extends Model
        {
            protected \$table = '{$table}';
            protected \$primaryKey = 'id';
            public \$timestamps = true;

            protected \$fillable = [
                //
            ];

            protected \$hidden = [
                //
            ];
        }

        PHP;

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $stub);
        $this->info("Model berhasil dibuat: app/Models/{$name}.php");

        if ($withMigration) {
            $this->makeMigration(["create_{$table}_table"]);
        }
    }

    // ---------- make:controller ----------

    public function makeController(array $args): void
    {
        $name = $this->getArgument($args, 0);

        if (!$name) {
            $this->error('Nama controller wajib diisi. Contoh: php jtech make:controller UserController');
            return;
        }

        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $path = constant('BASE_PATH') . "/app/Controllers/{$name}.php";

        if (file_exists($path)) {
            $this->error("Controller {$name} sudah ada di {$path}");
            return;
        }

        $stub = <<<PHP
        <?php

        namespace App\Controllers;

        use Novalites\RestApi\Http\Request;
        use Novalites\RestApi\Http\ApiResponse;

        class {$name}
        {
            public function index(Request \$request)
            {
                ApiResponse::success([]);
            }

            public function show(Request \$request, string \$id)
            {
                ApiResponse::success(['id' => \$id]);
            }

            public function store(Request \$request)
            {
                ApiResponse::success(\$request->all(), 201);
            }

            public function update(Request \$request, string \$id)
            {
                ApiResponse::success(['id' => \$id]);
            }

            public function destroy(Request \$request, string \$id)
            {
                ApiResponse::success(null);
            }
        }

        PHP;

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $stub);
        $this->info("Controller berhasil dibuat: app/Controllers/{$name}.php");
    }

    // ---------- make:migration ----------

    public function makeMigration(array $args): void
    {
        $name = $this->getArgument($args, 0);

        if (!$name) {
            $this->error('Nama migration wajib diisi. Contoh: php jtech make:migration create_users_table');
            return;
        }

        $timestamp = date('Y_m_d_His');
        $className = $this->studly($name);
        $filename = "{$timestamp}_{$name}.php";
        $path = constant('BASE_PATH') . "/database/migrations/{$filename}";

        // Deteksi apakah ini migration "create" -> generate stub dengan Schema::create
        $isCreate = str_starts_with($name, 'create_');
        preg_match('/create_(.+)_table/', $name, $matches);
        $table = $matches[1] ?? 'table_name';

        if ($isCreate) {
            $stub = <<<PHP
            <?php

            use Illuminate\Database\Capsule\Manager as Capsule;
            use Illuminate\Database\Schema\Blueprint;

            return new class
            {
                public function up(): void
                {
                    Capsule::schema()->create('{$table}', function (Blueprint \$table) {
                        \$table->ulid('id')->primary();
                        \$table->timestamps();
                    });
                }

                public function down(): void
                {
                    Capsule::schema()->dropIfExists('{$table}');
                }
            };

            PHP;
        } else {
            $stub = <<<PHP
            <?php

            use Illuminate\Database\Capsule\Manager as Capsule;
            use Illuminate\Database\Schema\Blueprint;

            return new class
            {
                public function up(): void
                {
                    Capsule::schema()->table('{$table}', function (Blueprint \$table) {
                        //
                    });
                }

                public function down(): void
                {
                    Capsule::schema()->dropIfExists('{$table}');
                }
            };

            PHP;
        }

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $stub);
        $this->info("Migration berhasil dibuat: database/migrations/{$filename}");
    }

    // ---------- migrate ----------

    public function migrate(array $args): void
    {
        Manager::init();
        $this->ensureMigrationsTable();

        $files = $this->getPendingMigrations();

        if (empty($files)) {
            $this->info('Ga ada migration baru yang perlu dijalankan.');
            return;
        }

        foreach ($files as $file) {
            $this->line("Migrating: {$file}");

            $migration = require constant('BASE_PATH') . "/database/migrations/{$file}";
            $migration->up();

            $this->recordMigration($file);
            $this->info("Migrated:  {$file}");
        }

        $this->info(count($files) . ' migration berhasil dijalankan.');
    }

    // ---------- migrate:rollback ----------

    public function migrateRollback(array $args): void
    {
        Manager::init();
        $this->ensureMigrationsTable();

        $lastBatch = \Illuminate\Database\Capsule\Manager::table('migrations')
            ->max('batch');

        if (!$lastBatch) {
            $this->warn('Ga ada migration buat di-rollback.');
            return;
        }

        $toRollback = \Illuminate\Database\Capsule\Manager::table('migrations')
            ->where('batch', $lastBatch)
            ->orderBy('id', 'desc')
            ->pluck('migration');

        foreach ($toRollback as $file) {
            $this->line("Rolling back: {$file}");

            $migration = require constant('BASE_PATH') . "/database/migrations/{$file}";
            $migration->down();

            \Illuminate\Database\Capsule\Manager::table('migrations')
                ->where('migration', $file)
                ->delete();

            $this->info("Rolled back:  {$file}");
        }
    }

    protected function migrateFresh(): void
    {
        Manager::init();

        // 1. Drop semua table DULU (termasuk tabel migrations lama)
        $this->warn('Menghapus semua table...');
        DB::dropAllTable();
        $this->info('Berhasil menghapus semua table.');

        // 2. BARU bikin ulang tabel migrations (setelah drop, bukan sebelum)
        $this->ensureMigrationsTable();

        $this->info('Menjalankan migrasi baru dalam: 2 detik');
        sleep(2);

        // 3. Karena tabel migrations baru dibuat ulang (kosong),
        //    semua file migration otomatis dianggap "pending"
        $files = $this->getAllMigrationFiles(); // bukan getPendingMigrations()

        if (empty($files)) {
            $this->info('Ga ada file migration yang ditemukan.');
            return;
        }

        foreach ($files as $file) {
            $this->line("Migrating: {$file}");

            $migration = require constant('BASE_PATH') . "/database/migrations/{$file}";
            $migration->up();

            $this->recordMigration($file);
            $this->info("Migrated:  {$file}");
        }

        $this->info(count($files) . ' migration berhasil dijalankan.');
    }

    // ---------- migrate:status ----------

    public function migrateStatus(array $args): void
    {
        Manager::init();
        $this->ensureMigrationsTable();

        $ran = \Illuminate\Database\Capsule\Manager::table('migrations')
            ->pluck('migration')
            ->toArray();

        $allFiles = $this->getAllMigrationFiles();

        $this->line();
        $this->line(str_pad('Migration', 60) . 'Status');
        $this->line(str_repeat('-', 75));

        foreach ($allFiles as $file) {
            $status = in_array($file, $ran, true) ? "\033[32mRan\033[0m" : "\033[33mPending\033[0m";
            $this->line(str_pad($file, 60) . $status);
        }

        $this->line();
    }

    // ---------- route:list ----------

    public function routeList(array $args): void
    {
        // Sesuaikan ini kalau lo udah punya Router class — asumsi ada file routes/api.php
        // yang return array of ['method' => ..., 'uri' => ..., 'handler' => ...]

        $allRoutes = glob(constant("BASE_PATH") . "/routes/*.php");

        require __DIR__ . "/../Support/default_route.php";
        foreach ($allRoutes as $file) {
            $basename = basename($file);
            if (!file_exists($file)) {
                $this->warn("File routes/{$basename} ga ditemukan.");
                return;
            }
            if ($basename === 'api.php') {
                Route::prefix('api')->group(function () use ($file) {
                    require $file;
                });
                continue;
            }
            require $file;
        }
        $routes = Route::routes();
        $this->line();
        $this->line(str_pad('Method', 10) . str_pad('URI', 35) . 'Handler');
        $this->line(str_repeat('-', 90));
        foreach ($routes as $route) {
            $action = "";
            if (\is_array($route['action'])) {
                $action .= $route['action'][0] . '@' . $route['action'][1];
            } else if (is_callable($route['action'])) {
                $action .= "-";
            }
            $this->line(
                str_pad($route['method'] ?? '-', 10)
                    . str_pad($route['path'] ?? '-', 35)
                    . ($action ?? '-')
            );
        }

        // dd($allRoute);

        $this->line();
    }

    // ---------- Migration helpers ----------

    protected function ensureMigrationsTable(): void
    {
        if (!DB::schema()->hasTable('migrations')) {
            DB::schema()->create('migrations', function ($table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
        }
    }

    protected function getAllMigrationFiles(): array
    {
        $dir = constant('BASE_PATH') . '/database/migrations';
        $this->ensureDirectoryExists($dir);

        $files = glob("{$dir}/*.php");
        $files = array_map('basename', $files);
        sort($files);

        return $files;
    }

    protected function getPendingMigrations(): array
    {
        $ran = \Illuminate\Database\Capsule\Manager::table('migrations')
            ->pluck('migration')
            ->toArray();

        return array_values(array_diff($this->getAllMigrationFiles(), $ran));
    }

    protected function recordMigration(string $file): void
    {
        $lastBatch = \Illuminate\Database\Capsule\Manager::table('migrations')->max('batch') ?? 0;

        \Illuminate\Database\Capsule\Manager::table('migrations')->insert([
            'migration' => $file,
            'batch'     => $lastBatch + 1,
        ]);
    }

    // ---------- Utility ----------

    protected function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }

    // ---------- Queue commands ----------

    public function queueWork(array $args): void
    {
        Manager::init();

        $queue = $this->getOption($args, 'queue', 'default');
        $sleep = (int) $this->getOption($args, 'sleep', 3);
        $once = in_array('--once', $args, true);

        $worker = new \Novalites\Queue\Worker();

        if ($once) {
            $processed = $worker->runOnce($queue);
            if (!$processed) {
                $this->line('Ga ada job yang perlu diproses.');
            }
            return;
        }

        $maxJobs = (int) $this->getOption($args, 'max-jobs', 0);
        $worker->work($queue, $sleep, $maxJobs);
    }

    public function queueFailed(array $args): void
    {
        Manager::init();

        $failed = \Illuminate\Database\Capsule\Manager::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->get();

        if ($failed->isEmpty()) {
            $this->info('Ga ada failed job.');
            return;
        }

        $this->line();
        $this->line(str_pad('ID', 6) . str_pad('Queue', 12) . str_pad('Failed At', 22) . 'Payload');
        $this->line(str_repeat('-', 80));

        foreach ($failed as $job) {
            $payload = json_decode($job->payload, true);
            $class = $payload['class'] ?? '-';

            $this->line(
                str_pad((string) $job->id, 6)
                    . str_pad($job->queue, 12)
                    . str_pad($job->failed_at, 22)
                    . $class
            );
        }
        $this->line();
    }

    public function queueRetry(array $args): void
    {
        Manager::init();

        $id = $this->getArgument($args, 0);

        if (!$id) {
            $this->error('ID job wajib diisi. Contoh: php jtech queue:retry 5');
            return;
        }

        $failedJob = \Illuminate\Database\Capsule\Manager::table('failed_jobs')->find($id);

        if (!$failedJob) {
            $this->error("Failed job #{$id} ga ditemukan.");
            return;
        }

        \Illuminate\Database\Capsule\Manager::table('jobs')->insert([
            'queue'        => $failedJob->queue,
            'payload'      => $failedJob->payload,
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => time(),
            'created_at'   => time(),
        ]);

        \Illuminate\Database\Capsule\Manager::table('failed_jobs')->where('id', $id)->delete();

        $this->info("Job #{$id} di-requeue.");
    }

    public function queueFlush(array $args): void
    {
        Manager::init();
        $count = \Illuminate\Database\Capsule\Manager::table('failed_jobs')->count();
        \Illuminate\Database\Capsule\Manager::table('failed_jobs')->truncate();
        $this->info("{$count} failed job dihapus.");
    }

    // ---------- Schedule commands ----------

    public function scheduleRun(array $args): void
    {
        Manager::init();

        $schedule = new \Novalites\Schedule\Schedule();

        $scheduleFile = constant('BASE_PATH') . '/app/Console/schedule.php';

        if (!file_exists($scheduleFile)) {
            $this->warn('File app/Console/schedule.php ga ditemukan.');
            return;
        }

        require $scheduleFile; // isi file ini akan pakai variable $schedule

        $schedule->run();
    }

    public function scheduleList(array $args): void
    {
        $schedule = new \Novalites\Schedule\Schedule();

        $scheduleFile = constant('BASE_PATH') . '/app/Console/schedule.php';

        if (!file_exists($scheduleFile)) {
            $this->warn('File app/Console/schedule.php ga ditemukan.');
            return;
        }

        require $scheduleFile;

        $this->line();
        $this->line(str_pad('Expression', 15) . str_pad('Description', 40) . 'Next Due');
        $this->line(str_repeat('-', 80));

        foreach ($schedule->getEvents() as $event) {
            $due = $event->isDue() ? "\033[32mDue sekarang\033[0m" : '-';
            $this->line(
                str_pad($event->getExpression(), 15)
                    . str_pad($event->getDescription(), 40)
                    . $due
            );
        }
        $this->line();
    }

    public function storageLink()
    {
        $targetDir = constant('BASE_PATH') . "/storage/uploads";
        $linkDir = constant('BASE_PATH') . "/public/storage";

        if (file_exists($linkDir)) {
            $this->warn("Symlink atau folder public/storage sudah ada");
            die;
        }


        if (symlink($targetDir, $linkDir)) {
            $this->info("Mantap! Symlink berhasil dibuat.");
        } else {
            $this->error("Gagal bikin symlink. Cek masalah permission di bawah.");
        }
    }

    // ---------- cache:clear ----------

    public function cacheClear(array $args): void
    {
        Manager::init();

        $driver = $this->getOption($args, 'driver');

        try {
            if ($driver !== null) {
                // Force clear driver tertentu aja, override config
                $this->clearSpecificDriver($driver);
            } else {
                \Novalites\Cache\Cache::flush();
            }

            $this->info('Application cache berhasil dihapus.');
        } catch (\Throwable $e) {
            $this->error('Gagal menghapus cache: ' . $e->getMessage());
        }
    }

    protected function clearSpecificDriver(string $driver): void
    {
        match ($driver) {
            'database' => $this->isDatabaseCacheExists(),
            'redis'    => (new \Novalites\Cache\RedisCacheDriver(
                (require constant('BASE_PATH') . '/config/cache.php')['drivers']['redis']
            ))->flush(),
            default    => throw new \InvalidArgumentException("Driver '{$driver}' ga dikenali."),
        };
    }

    protected function isDatabaseCacheExists()
    {
        $hasTable = \Illuminate\Database\Capsule\Manager::schema()->hasTable('cache');

        if (!$hasTable) {
            $this->info("Table cache belum ada!");
            return;
        }
        \Illuminate\Database\Capsule\Manager::table('cache')->truncate();
        $this->info("Berhasil membersihkan cache database");
    }

    // ---------- view:clear ----------

    public function viewClear(array $args): void
    {
        $this->bootTemplateEngine();

        $count = \Novalites\Templating\TemplateEngine::clearCache();
        $this->info("{$count} file view cache berhasil dihapus.");
    }

    protected function bootTemplateEngine(): void
    {
        $basePath = constant('BASE_PATH');

        \Novalites\Templating\TemplateEngine::setViewsPath($basePath . '/resources/views');
        \Novalites\Templating\TemplateEngine::setCachePath($basePath . '/storage/framework/views');
    }

    // ---------- config:clear ----------

    public function configClear(array $args): void
    {
        $cacheFile = constant('BASE_PATH') . '/storage/framework/config.php';

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            $this->info('Config cache berhasil dihapus.');
        } else {
            $this->info('Ga ada config cache yang perlu dihapus.');
        }
    }

    // ---------- route:clear ----------

    public function routeClear(array $args): void
    {
        $cacheFile = constant('BASE_PATH') . '/storage/framework/routes.php';

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            $this->info('Route cache berhasil dihapus.');
        } else {
            $this->info('Ga ada route cache yang perlu dihapus.');
        }
    }

    // ---------- session:clear ----------

    public function sessionClear(array $args): void
    {
        Manager::init();

        $config = require constant('BASE_PATH') . '/config/session.php';

        if ($config['driver'] === 'database') {
            $count = \Illuminate\Database\Capsule\Manager::table('sessions')->count();
            \Illuminate\Database\Capsule\Manager::table('sessions')->truncate();
            $this->info("{$count} session (database) berhasil dihapus.");
            return;
        }

        // Driver file
        $path = $config['path'] ?? sys_get_temp_dir() . '/jtech_sessions';
        $count = $this->clearDirectory($path, 'sess_*');
        $this->info("{$count} file session berhasil dihapus.");
    }

    // ---------- log:clear ----------

    public function logClear(array $args): void
    {
        $logPath = constant('BASE_PATH') . '/storage/logs';

        if (!is_dir($logPath)) {
            $this->info('Ga ada folder log yang ditemukan.');
            return;
        }

        $files = glob("{$logPath}/*.log");
        $count = 0;

        foreach ($files as $file) {
            // Kosongin isi file, tapi ga hapus filenya (biar log writer ga error nyari file)
            file_put_contents($file, '');
            $count++;
        }

        $this->info("{$count} file log berhasil dikosongkan.");
    }

    // ---------- optimize:clear (hapus semua sekaligus) ----------

    public function optimizeClear(array $args): void
    {
        $this->line();
        $this->info('Membersihkan semua cache...');
        $this->line();

        $this->line('→ Application cache');
        $this->cacheClear($args);

        $this->line('→ View cache');
        $this->viewClear($args);

        $this->line('→ Config cache');
        $this->configClear($args);

        $this->line('→ Route cache');
        $this->routeClear($args);

        $this->line();
        $this->info('Semua cache berhasil dibersihkan! 🎉');
    }

    // ---------- Helper ----------

    protected function clearDirectory(string $path, string $pattern = '*'): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $files = glob("{$path}/{$pattern}");
        $count = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    // tambahin ke $commands

    public function optimize(array $args): void
    {
        $this->line();
        $this->info('Meng-cache config & route buat production...');

        $dir = constant('BASE_PATH') . '/storage/framework';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Cache config — gabung semua file di config/ jadi satu array besar
        $configPath = constant('BASE_PATH') . '/config';
        $merged = [];
        foreach (glob("{$configPath}/*.php") as $file) {
            $key = basename($file, '.php');
            $merged[$key] = require $file;
        }
        file_put_contents(
            "{$dir}/config.php",
            '<?php return ' . var_export($merged, true) . ';'
        );
        $this->info('Config berhasil di-cache.');

        // Cache route — serialize semua route yang udah terdaftar
        // (asumsi routes udah di-load duluan sebelum command ini jalan)
        // Biasanya butuh load routes dulu manual di sini kalau mau generate cache beneran

        $this->line();
        $this->info('Optimize selesai.');
    }


    public function installApi(array $args = []): void
    {
        $this->line();
        $this->info('Menginstall fitur REST API (token authentication)...');
        $this->line();

        $force = in_array('--force', $args, true);

        $this->installMigration($force);
        $this->installPersonalAccessTokenModel($force);
        $this->installNewAccessToken($force);
        $this->installHasApiTokensTrait($force);
        $this->installAuthenticateMiddleware($force);
        $this->installApiRoutes($force);
        $this->registerMiddlewareReminder();

        $this->line();
        $this->info('Instalasi API selesai! 🎉');
        $this->line();
        $this->warn('Langkah selanjutnya:');
        $this->line('  1. Tambahin "use Novalites\\Auth\\HasApiTokens;" ke Model User kamu');
        $this->line('  2. Jalankan: php jtech migrate');
        $this->line('  3. Daftarin middleware alias di bootstrap (lihat pesan di atas)');
        $this->line();
    }

    // ---------- Migration ----------

    protected function installMigration(bool $force): void
    {
        $dir = constant('BASE_PATH') . '/database/migrations';

        if (!$force && $this->migrationExists($dir, 'create_personal_access_tokens_table')) {
            $this->warn('[skip] Migration personal_access_tokens sudah ada.');
            return;
        }

        $path = $dir . '/' . date('Y_m_d_His') . '_create_personal_access_tokens_table.php';

        $stub = <<<'PHP'
    <?php

    use Illuminate\Database\Capsule\Manager as Capsule;
    use Illuminate\Database\Schema\Blueprint;

    return new class
    {
        public function up(): void
        {
            Capsule::schema()->create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('tokenable_type');
                $table->unsignedBigInteger('tokenable_id');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['tokenable_type', 'tokenable_id']);
            });
        }

        public function down(): void
        {
            Capsule::schema()->dropIfExists('personal_access_tokens');
        }
    };

    PHP;

        $this->writeFile($path, $stub);
        $this->info("[created] Migration: {$path}");
    }

    protected function migrationExists(string $dir, string $suffix): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        return !empty(glob("{$dir}/*_{$suffix}.php"));
    }

    // ---------- Model PersonalAccessToken ----------

    protected function installPersonalAccessTokenModel(bool $force): void
    {
        $path = constant('BASE_PATH') . '/app/Models/PersonalAccessToken.php';

        if (!$force && file_exists($path)) {
            $this->warn('[skip] app/Models/PersonalAccessToken.php sudah ada.');
            return;
        }

        $stub = <<<'PHP'
    <?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class PersonalAccessToken extends Model
    {
        protected $table = 'personal_access_tokens';

        protected $fillable = [
            'tokenable_type',
            'tokenable_id',
            'name',
            'token',
            'abilities',
            'last_used_at',
            'expires_at',
        ];

        protected $hidden = ['token'];

        public function tokenable()
        {
            return $this->morphTo();
        }

        public function getAbilitiesArray(): array
        {
            return json_decode($this->abilities ?? '[]', true) ?: ['*'];
        }

        public function can(string $ability): bool
        {
            $abilities = $this->getAbilitiesArray();
            return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
        }

        public function isExpired(): bool
        {
            if ($this->expires_at === null) {
                return false;
            }
            return strtotime($this->expires_at) < time();
        }
    }

    PHP;

        $this->writeFile($path, $stub);
        $this->info("[created] Model: {$path}");
    }

    // ---------- NewAccessToken ----------

    protected function installNewAccessToken(bool $force): void
    {
        $path = constant('BASE_PATH') . '/app/Auth/NewAccessToken.php';

        if (!$force && file_exists($path)) {
            $this->warn('[skip] app/Auth/NewAccessToken.php sudah ada.');
            return;
        }

        $stub = <<<'PHP'
    <?php

    namespace App\Auth;

    use App\Models\PersonalAccessToken;

    class NewAccessToken
    {
        public function __construct(
            public readonly PersonalAccessToken $accessToken,
            public readonly string $plainTextToken
        ) {}

        public function toArray(): array
        {
            return [
                'id'         => $this->accessToken->id,
                'name'       => $this->accessToken->name,
                'token'      => $this->plainTextToken,
                'abilities'  => $this->accessToken->getAbilitiesArray(),
                'expires_at' => $this->accessToken->expires_at,
            ];
        }
    }

    PHP;

        $this->writeFile($path, $stub);
        $this->info("[created] {$path}");
    }

    // ---------- Trait HasApiTokens ----------

    protected function installHasApiTokensTrait(bool $force): void
    {
        $path = constant('BASE_PATH') . '/app/Auth/HasApiTokens.php';

        if (!$force && file_exists($path)) {
            $this->warn('[skip] app/Auth/HasApiTokens.php sudah ada.');
            return;
        }

        $stub = <<<'PHP'
    <?php

    namespace App\Auth;

    use App\Models\PersonalAccessToken;

    trait HasApiTokens
    {
        protected ?PersonalAccessToken $currentAccessToken = null;

        public function tokens()
        {
            return $this->morphMany(PersonalAccessToken::class, 'tokenable');
        }

        public function createToken(string $name, array $abilities = ['*'], ?string $expiresAt = null): NewAccessToken
        {
            $plainTextToken = $this->generatePlainTextToken();

            $accessToken = $this->tokens()->create([
                'name'       => $name,
                'token'      => hash('sha256', $plainTextToken),
                'abilities'  => json_encode($abilities),
                'expires_at' => $expiresAt,
            ]);

            return new NewAccessToken($accessToken, $plainTextToken);
        }

        protected function generatePlainTextToken(): string
        {
            return bin2hex(random_bytes(32));
        }

        public function revokeAllTokens(): int
        {
            return $this->tokens()->delete();
        }

        public function revokeToken(int $tokenId): bool
        {
            return $this->tokens()->where('id', $tokenId)->delete() > 0;
        }

        public function revokeCurrentToken(): bool
        {
            if ($this->currentAccessToken === null) {
                return false;
            }
            return $this->currentAccessToken->delete();
        }

        public function withAccessToken(PersonalAccessToken $token): static
        {
            $this->currentAccessToken = $token;
            return $this;
        }

        public function currentAccessToken(): ?PersonalAccessToken
        {
            return $this->currentAccessToken;
        }

        public function tokenCan(string $ability): bool
        {
            return $this->currentAccessToken?->can($ability) ?? false;
        }

        public static function findByToken(string $plainTextToken): ?static
        {
            $hashed = hash('sha256', $plainTextToken);

            $accessToken = PersonalAccessToken::where('token', $hashed)->first();

            if ($accessToken === null || $accessToken->isExpired()) {
                return null;
            }

            $tokenableClass = $accessToken->tokenable_type;
            $user = $tokenableClass::find($accessToken->tokenable_id);

            if ($user === null) {
                return null;
            }

            $accessToken->update(['last_used_at' => date('Y-m-d H:i:s')]);

            return $user->withAccessToken($accessToken);
        }
    }

    PHP;

        $this->writeFile($path, $stub);
        $this->info("[created] {$path}");
    }

    // ---------- Middleware ----------

    protected function installAuthenticateMiddleware(bool $force): void
    {
        $path = constant('BASE_PATH') . '/app/Middleware/AuthenticateApiToken.php';

        if (!$force && file_exists($path)) {
            $this->warn('[skip] app/Middleware/AuthenticateApiToken.php sudah ada.');
            return;
        }

        $stub = <<<'PHP'
    <?php

    namespace App\Middleware;

    use Novalites\Http\Request;
    use Novalites\Http\Response;
    use App\Models\User;

    class AuthenticateApiToken
    {
        protected string $model = User::class;

        public function handle(Request $request): void
        {
            $token = $this->extractBearerToken($request);

            if ($token === null) {
                $this->unauthorized('Token tidak ditemukan.');
            }

            $user = $this->model::findByToken($token);

            if ($user === null) {
                $this->unauthorized('Token tidak valid atau sudah kadaluarsa.');
            }

            $request->authUser = $user;
        }

        protected function extractBearerToken(Request $request): ?string
        {
            $header = $request->header('authorization', '');

            if (!str_starts_with($header, 'Bearer ')) {
                return null;
            }

            $token = substr($header, 7);

            return $token !== '' ? $token : null;
        }

        protected function unauthorized(string $message): never
        {
            Response::error($message, 401);
        }
    }

    PHP;

        $this->writeFile($path, $stub);
        $this->info("[created] {$path}");
    }

    // ---------- Route stub ----------

    protected function installApiRoutes(bool $force): void
    {
        $path = constant('BASE_PATH') . '/routes/api.php';

        if (!$force && file_exists($path)) {
            $this->warn('[skip] routes/api.php sudah ada, dilewati (route auth ga ditambahin otomatis).');
            return;
        }

        $stub = <<<'PHP'
    <?php

    use Novalites\Support\Facades\Route;
    use Novalites\Http\Response;
    use App\Controllers\AuthController;

    Route::get('/', function () {
        Response::success([
            'message' => 'REST API berhasil terinstall',
        ]);
    });

    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth.token')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    PHP;

        $this->writeFile($path, $stub);
        $this->info("[created] {$path}");

        $this->installAuthController($force);
    }

    protected function installAuthController(bool $force): void
    {
        $path = constant('BASE_PATH') . '/app/Controllers/AuthController.php';

        if (!$force && file_exists($path)) {
            $this->warn('[skip] app/Controllers/AuthController.php sudah ada.');
            return;
        }

        $stub = <<<'PHP'
    <?php

    namespace App\Controllers;

    use Novalites\Http\Request;
    use Novalites\Http\Response;
    use App\Models\User;

    class AuthController
    {
        public function login(Request $request)
        {
            $credentials = $request->validate([
                'email'    => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $credentials['email'])->first();

            if ($user === null || !password_verify($credentials['password'], $user->password)) {
                Response::error('Email atau password salah.', 401);
            }

            $token = $user->createToken('api-token');

            Response::success([
                'user'  => $user,
                'token' => $token->plainTextToken,
            ], 'Login berhasil');
        }

        public function logout(Request $request)
        {
            $request->user()->revokeCurrentToken();
            Response::success(null, 'Berhasil logout dari device ini.');
        }

        public function logoutAll(Request $request)
        {
            $count = $request->user()->revokeAllTokens();
            Response::success(['revoked' => $count], 'Berhasil logout dari semua device.');
        }
    }

    PHP;

        $this->writeFile($path, $stub);
        $this->info("[created] {$path}");
    }

    // ---------- Reminder ----------

    protected function registerMiddlewareReminder(): void
    {
        $this->line();
        $this->warn('Tambahin ini di withMiddleware() pada bootstrap kamu:');
        $this->line();
        $this->line('  use App\Middleware\AuthenticateApiToken;');
        $this->line();
        $this->line('  $middleware->alias([');
        $this->line("      'auth.token' => AuthenticateApiToken::class,");
        $this->line('  ]);');
        $this->line();
    }

    // ---------- Helper umum ----------

    protected function writeFile(string $path, string $content): void
    {
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
    }
}
