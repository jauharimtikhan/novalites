<?php

namespace Novalites\Composer;

use Composer\Installer\PackageEvent;

class PackageInstallHandler
{
    /**
     * Mapping: nama package -> command yang mau di-jalanin otomatis kalau package itu ke-install.
     */
    protected static array $reactions = [
        'predis/predis'          => 'cache:clear', // misal abis install redis client, clear cache biar re-detect driver
        'vlucas/phpdotenv'       => null,           // ga perlu aksi apa2
        'illuminate/database'    => 'migrate:status',
    ];

    public static function handle(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        // Ambil nama package yang baru di-install
        if (method_exists($operation, 'getPackage')) {
            $packageName = $operation->getPackage()->getName();

            if (isset(self::$reactions[$packageName]) && self::$reactions[$packageName] !== null) {
                $command = self::$reactions[$packageName];
                echo "→ Menjalankan 'php jtech {$command}' karena {$packageName} baru di-install...\n";
                passthru('php jtech ' . escapeshellarg($command));
            }
        }
    }
}
