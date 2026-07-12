<?php

namespace Novalites\Container;

use Closure;
use Novalites\Exception\ContainerException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionException;

class Container
{
    /**
     * Instance singleton container itu sendiri (biar bisa dipanggil global)
     */
    protected static ?Container $instance = null;

    /**
     * Semua binding yang terdaftar: ['abstract' => ['concrete' => Closure, 'shared' => bool]]
     */
    protected array $bindings = [];

    /**
     * Instance yang udah di-resolve buat singleton (biar ga dibuat ulang)
     */
    protected array $instances = [];

    /**
     * Alias: ['alias' => 'abstract_asli']
     */
    protected array $aliases = [];

    /**
     * Contextual binding: [ConcreteClass::class => ['NeedsAbstract' => 'GiveConcrete']]
     */
    protected array $contextual = [];

    /**
     * Stack buat tracking dependency chain (buat deteksi circular dependency)
     */
    protected array $buildStack = [];



    // ---------- Singleton container ----------

    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public static function setInstance(?Container $container): void
    {
        self::$instance = $container;
    }

    // ---------- Binding ----------

    /**
     * Daftarin binding biasa (dibuat baru tiap kali di-resolve)
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $abstract = $this->normalize($abstract);

        // Kalau concrete ga dikasih, anggap abstract = concrete (self-binding)
        if ($concrete === null) {
            $concrete = $abstract;
        }

        // Kalau concrete berupa string class name, wrap jadi Closure
        if (!$concrete instanceof Closure) {
            $concrete = function (Container $container, array $params = []) use ($concrete) {
                return $container->build($concrete, $params);
            };
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    /**
     * Daftarin binding sebagai singleton (cuma dibuat sekali, instance-nya di-cache)
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Daftarin instance yang udah jadi langsung (langsung dianggap resolved)
     */
    public function instance(string $abstract, mixed $instance): mixed
    {
        $abstract = $this->normalize($abstract);
        $this->instances[$abstract] = $instance;
        return $instance;
    }

    /**
     * Bikin alias, misal 'db' -> App\Services\Database::class
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Contextual binding: "kalau lagi build class X, dan butuh interface Y, kasih Z"
     * Contoh: $container->when(ReportController::class)->needs(Logger::class)->give(FileLogger::class);
     */
    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $this->normalize($concrete));
    }

    public function addContextualBinding(string $concrete, string $abstract, Closure|string $implementation): void
    {
        $this->contextual[$concrete][$this->normalize($abstract)] = $implementation;
    }

    // ---------- Resolving ----------

    /**
     * Resolve abstract jadi instance konkret. Ini method utama.
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->normalize($abstract);

        // Kalau udah ada instance singleton yang di-cache, langsung balikin
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        // Kalau concrete-nya Closure custom (dari bind()), panggil langsung
        if ($concrete instanceof Closure) {
            $object = $concrete($this, $parameters);
        } else {
            $object = $this->build($concrete, $parameters);
        }

        // Simpan ke instances kalau binding-nya shared/singleton
        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    protected function getConcrete(string $abstract): mixed
    {
        // Cek contextual binding dulu (berdasarkan class yang lagi di-build)
        if (!empty($this->buildStack)) {
            $currentlyBuilding = end($this->buildStack);
            if (isset($this->contextual[$currentlyBuilding][$abstract])) {
                return $this->contextual[$currentlyBuilding][$abstract];
            }
        }

        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        // Ga ada binding terdaftar -> anggap abstract-nya sendiri adalah class konkret
        return $abstract;
    }

    protected function isShared(string $abstract): bool
    {
        return $this->bindings[$abstract]['shared'] ?? false;
    }

    /**
     * Auto-wiring: build instance class via reflection, resolve semua constructor dependency.
     */
    public function build(string|Closure $concrete, array $parameters = []): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new ContainerException("Class [{$concrete}] tidak ditemukan: " . $e->getMessage());
        }

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class [{$concrete}] ga bisa di-instantiate (interface/abstract class).");
        }

        $constructor = $reflector->getConstructor();

        // Ga ada constructor -> langsung instantiate tanpa dependency
        if ($constructor === null) {
            return new $concrete();
        }

        // Deteksi circular dependency
        if (in_array($concrete, $this->buildStack, true)) {
            $chain = implode(' -> ', [...$this->buildStack, $concrete]);
            throw new ContainerException("Circular dependency terdeteksi: {$chain}");
        }

        $this->buildStack[] = $concrete;

        try {
            $dependencies = $this->resolveDependencies(
                $constructor->getParameters(),
                $parameters,
                $concrete
            );
        } finally {
            array_pop($this->buildStack);
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve tiap parameter constructor: dari $parameters manual, atau auto-wire dari type-hint.
     */
    protected function resolveDependencies(array $reflectionParams, array $primitives, string $concrete): array
    {
        $dependencies = [];

        foreach ($reflectionParams as $param) {
            $name = $param->getName();

            // 1. Kalau parameter-nya di-pass manual (misal via make('Class', ['config' => [...]]))
            if (array_key_exists($name, $primitives)) {
                $dependencies[] = $primitives[$name];
                continue;
            }

            $type = $param->getType();

            // 2. Kalau ga ada type-hint atau tipe-nya builtin (string, int, array, dll)
            if ($type === null || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                    continue;
                }

                if ($param->allowsNull()) {
                    $dependencies[] = null;
                    continue;
                }

                throw new ContainerException(
                    "Ga bisa resolve parameter [\${$name}] di class [{$concrete}] — ga ada type-hint dan ga ada default value."
                );
            }

            // 3. Type-hint berupa class/interface -> auto-wire lewat make() (rekursif)
            $className = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;

            try {
                $dependencies[] = $this->make($className);
            } catch (ContainerException $e) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                    continue;
                }
                if ($param->allowsNull()) {
                    $dependencies[] = null;
                    continue;
                }
                throw $e;
            }
        }

        return $dependencies;
    }

    /**
     * Panggil method/closure apapun dengan auto-inject dependency-nya.
     * Contoh: $container->call([UserController::class, 'index']);
     *         $container->call(fn(Request $r) => ...);
     */
    public function call(array|Closure|string $callback, array $parameters = []): mixed
    {
        if ($callback instanceof Closure) {
            $reflection = new \ReflectionFunction($callback);
            $dependencies = $this->resolveDependencies($reflection->getParameters(), $parameters, 'Closure');
            return $callback(...$dependencies);
        }

        if (is_array($callback)) {
            [$classOrInstance, $method] = $callback;
            $instance = is_string($classOrInstance) ? $this->make($classOrInstance) : $classOrInstance;

            $reflection = new \ReflectionMethod($instance, $method);
            $dependencies = $this->resolveDependencies($reflection->getParameters(), $parameters, get_class($instance));

            return $reflection->invokeArgs($instance, $dependencies);
        }

        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback, 2);
            return $this->call([$class, $method], $parameters);
        }

        throw new ContainerException('Format callback ga valid buat di-call.');
    }

    // ---------- Helper ----------

    public function has(string $abstract): bool
    {
        $abstract = $this->normalize($abstract);
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    public function bound(string $abstract): bool
    {
        return $this->has($abstract);
    }

    protected function normalize(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }


    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->contextual = [];
        $this->buildStack = [];
    }
}
