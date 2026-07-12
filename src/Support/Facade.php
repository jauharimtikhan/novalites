<?php

namespace Novalites\Support;

use Novalites\Container\Container;
use RuntimeException;

abstract class Facade
{
    /**
     * Cache instance yang udah di-resolve, biar ga resolve ulang tiap panggil.
     */
    protected static array $resolvedInstances = [];

    /**
     * Container instance yang dipakai facade (bisa di-swap buat testing).
     */
    protected static ?Container $container = null;

    /**
     * WAJIB di-override di tiap Facade turunan.
     * Return abstract binding key yang terdaftar di Container.
     */
    protected static function getFacadeAccessor(): string
    {
        throw new RuntimeException(
            'Facade [' . static::class . '] harus meng-override method getFacadeAccessor().'
        );
    }

    public static function setContainer(Container $container): void
    {
        static::$container = $container;
    }

    protected static function getContainer(): Container
    {
        return static::$container ??= Container::getInstance();
    }

    /**
     * Resolve instance asli dari container berdasarkan accessor.
     */
    protected static function resolveFacadeInstance(): mixed
    {
        $accessor = static::getFacadeAccessor();

        // Kalau accessor-nya udah berupa instance langsung (bukan string binding key)
        if (is_object($accessor)) {
            return $accessor;
        }

        if (isset(static::$resolvedInstances[$accessor])) {
            return static::$resolvedInstances[$accessor];
        }

        $instance = static::getContainer()->make($accessor);

        return static::$resolvedInstances[$accessor] = $instance;
    }

    /**
     * Proxy semua static call ke method instance asli.
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::resolveFacadeInstance();

        if (!method_exists($instance, $method)) {
            throw new RuntimeException(
                'Method [' . $method . '] tidak ditemukan di class [' . get_class($instance) . '] (Facade: ' . static::class . ').'
            );
        }

        return $instance->{$method}(...$args);
    }

    /**
     * Buat testing — replace instance yang di-resolve facade dengan mock/fake.
     */
    public static function swap(mixed $instance): void
    {
        $accessor = static::getFacadeAccessor();
        static::getContainer()->instance($accessor, $instance);
        static::$resolvedInstances[$accessor] = $instance;
    }

    /**
     * Reset cache resolved instance (berguna antar test case).
     */
    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstances = [];
    }
}
