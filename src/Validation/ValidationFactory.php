<?php

namespace Novalites\Validation;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\DatabasePresenceVerifier;
use Novalites\Database\Manager;

class ValidationFactory
{
    private static ?Factory $factory = null;

    public function __construct() {}

    public static function getInstance(string $locale = 'en'): Factory
    {
        if (self::$factory === null) {
            $filesystem = new Filesystem();

            // 1. Logika Deteksi Root Path Parent Project
            $basePath = null;
            if (defined('BASE_PATH')) {
                // Gunakan constant() untuk mencegah bentrok namespace
                $basePath = constant('BASE_PATH');
            } else {
                $vendorDir = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
                $vendorPos = strpos(__DIR__, $vendorDir);

                if ($vendorPos !== false) {
                    $basePath = substr(__DIR__, 0, $vendorPos);
                } else {
                    // Fallback jika tidak dieksekusi dari dalam folder vendor
                    $basePath = dirname(__DIR__, 2);
                }
            }

            // 2. Tentukan Lokasi Folder 'lang'
            $parentLangPath = $basePath . DIRECTORY_SEPARATOR . 'lang';

            // realpath() digunakan untuk membersihkan format path (menghilangkan /../)
            $libraryLangPath = realpath(__DIR__ . '/../../lang') ?: (__DIR__ . '/../../lang');

            // Prioritas: Jika parent project punya folder lang sendiri, gunakan itu.
            // Jika tidak ada, fallback ke folder lang bawaan library.
            $primaryLangPath = is_dir($parentLangPath) ? $parentLangPath : $libraryLangPath;

            $loader = new FileLoader($filesystem, $primaryLangPath);

            // 3. Daftarkan namespace library sebagai backup
            // Jika parent project menimpa folder lang, kita tetap daftarkan lang library
            // agar bisa diakses eksplisit dengan format 'jtech::validation.required'
            if (is_dir($libraryLangPath)) {
                $loader->addNamespace('jtech', $libraryLangPath);
            }

            $translator = new Translator($loader, $locale);

            $factory = new Factory($translator);

            // Wajib biar rule 'unique' dan 'exists' bisa jalan (butuh koneksi DB Eloquent)
            $factory->setPresenceVerifier(
                new DatabasePresenceVerifier(Manager::getInstance()->getDatabaseManager())
            );

            self::$factory = $factory;
        }

        return self::$factory;
    }

    /**
     * Shortcut langsung bikin validator, mirip Validator::make() di Laravel
     */
    public static function make(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        return self::getInstance()->make($data, $rules, $messages, $attributes);
    }
}
