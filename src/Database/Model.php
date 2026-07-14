<?php

namespace Novalites\Database;

use Novalites\Database\Manager;
use Novalites\Database\Relations\HasOne;
use Novalites\Database\Relations\HasMany;
use Novalites\Database\Relations\BelongsTo;
use Novalites\Database\Relations\BelongsToMany;
use Novalites\Database\Relations\MorphOne;
use Novalites\Database\Relations\MorphMany;
use Novalites\Database\Relations\MorphTo;
use Novalites\Database\Relations\MorphToMany;
use Novalites\Support\Collection;
use Novalites\Support\Str;
use JsonSerializable;

abstract class Model implements JsonSerializable
{
    protected string $table;
    protected ?string $connection = null;

    // ── PRIMARY KEY CONFIG (override-able kayak Laravel) ─────
    protected string $primaryKey = 'id';
    public bool $incrementing = true;
    protected string $keyType = 'int'; // 'int' | 'uuid' | 'ulid'

    public bool $timestamps = true;

    protected array $fillable = [];
    protected array $guarded = ['*'];
    protected array $casts = [];
    protected array $hidden = [];
    protected array $visible = [];
    protected array $appends = [];

    protected array $attributes = [];
    protected array $original = [];
    protected array $relations = [];

    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // ── TABLE / CONNECTION ───────────────────────────────

    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        $class = (new \ReflectionClass($this))->getShortName();
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
        return $snake . 's';
    }

    // ── PRIMARY KEY ACCESSORS ─────────────────────────────

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function setKeyName(string $key): static
    {
        $this->primaryKey = $key;
        return $this;
    }

    public function getKeyType(): string
    {
        return $this->keyType;
    }

    public function setKeyType(string $type): static
    {
        $this->keyType = $type;
        return $this;
    }

    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }

    public function setIncrementing(bool $value): static
    {
        $this->incrementing = $value;
        return $this;
    }

    public function getKey(): mixed
    {
        $value = $this->getAttribute($this->primaryKey);
        return $this->castKeyType($value);
    }

    protected function castKeyType(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->keyType) {
            'int'    => (int) $value,
            'uuid' => (string) $value,
            'ulid' => (string) $value,
            default  => $value,
        };
    }

    /**
     * Dipanggil otomatis sebelum insert kalau key non-incrementing
     * dan belum ada value (misal UUID primary key).
     * Override method ini di child model kalau mau strategi lain
     * (misal ULID, custom prefix, dst).
     */
    protected function generateKeyValue(): mixed
    {
        if ($this->keyType === 'uuid') {
            return Str::uuid();
        }
        if ($this->keyType === 'ulid') {
            return Str::ulid();
        }

        return null;
    }

    protected static function connection(): \PDO
    {
        $instance = new static();
        return Manager::getConnection($instance->connection);
    }

    // ── MASS ASSIGNMENT ──────────────────────────────────

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    protected function isFillable(string $key): bool
    {
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable, true);
        }

        if ($this->guarded === ['*']) {
            return false;
        }

        return !in_array($key, $this->guarded, true);
    }

    // ── ATTRIBUTE GET/SET ─────────────────────────────────

    public function setAttribute(string $key, mixed $value): void
    {
        $mutator = 'set' . $this->studly($key) . 'Attribute';
        if (method_exists($this, $mutator)) {
            $this->$mutator($value);
            return;
        }

        if ($this->hasCast($key)) {
            $value = $this->castForStorage($key, $value);
        }

        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key): mixed
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key) && !array_key_exists($key, $this->attributes)) {
            return $this->getRelationValue($key);
        }

        $accessor = 'get' . $this->studly($key) . 'Attribute';
        $value = $this->attributes[$key] ?? null;

        if ($this->hasCast($key)) {
            $value = $this->castAttribute($key, $value);
        }

        if (method_exists($this, $accessor)) {
            return $this->$accessor($value);
        }

        return $value;
    }

    protected function getRelationValue(string $key): mixed
    {
        if (!method_exists($this, $key)) {
            return null;
        }

        $relation = $this->$key();

        if (!$relation instanceof Relations\Relation) {
            return null;
        }

        $result = $relation->getResults();
        $this->relations[$key] = $result;

        return $result;
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]) || isset($this->relations[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    protected function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }

    // ── CASTING ───────────────────────────────────────────

    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->casts[$key]) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            'object' => is_string($value) ? json_decode($value) : $value,
            'date' => $this->asDateTime($value),
            'datetime' => $this->asDateTime($value),
            default => $value,
        };
    }

    protected function asDateTime(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value);
        }

        // asumsi kolom DB nyimpen waktu dalam timezone default aplikasi (lihat config di bawah)
        $tz = new \DateTimeZone($this->getDateTimezone());

        return new \DateTimeImmutable($value, $tz);
    }

    /**
     * Timezone yang dipakai buat interpretasi value datetime dari DB.
     * Override method ini di child model kalau mau beda per-model,
     * atau ubah default-nya di sini biar global.
     */
    protected function getDateTimezone(): string
    {
        return date_default_timezone_get(); // ambil dari php.ini / config app, misal 'Asia/Jakarta'
    }

    protected function castForStorage(string $key, mixed $value): mixed
    {
        return match ($this->casts[$key]) {
            'array', 'json' => is_array($value) || is_object($value) ? json_encode($value) : $value,
            'bool', 'boolean' => (int) $value,
            'date' => $this->formatDateForStorage($value, 'Y-m-d'),
            'datetime' => $this->formatDateForStorage($value, 'Y-m-d H:i:s'),
            default => $value,
        };
    }

    protected function formatDateForStorage(mixed $value, string $format): string
    {
        if ($value instanceof \DateTimeInterface) {
            // convert ke timezone default app dulu sebelum disimpan,
            // biar DB tetap konsisten walau input-nya dari timezone lain
            $tz = new \DateTimeZone($this->getDateTimezone());
            $dt = $value instanceof \DateTimeImmutable ? $value : \DateTimeImmutable::createFromMutable($value);
            return $dt->setTimezone($tz)->format($format);
        }

        return $value;
    }

    // ── DIRTY TRACKING ────────────────────────────────────

    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            return ($this->attributes[$key] ?? null) !== ($this->original[$key] ?? null);
        }

        return $this->getDirty() !== [];
    }

    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    // ── QUERY BUILDER PROXY ───────────────────────────────

    public static function query(): ModelQueryBuilder
    {
        $instance = new static();
        return new ModelQueryBuilder(static::connection(), $instance->getTable(), static::class);
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        return static::query()->$method(...$args);
    }

    public function __call(string $method, array $args): mixed
    {
        return static::query()->$method(...$args);
    }

    public static function all(): Collection
    {
        return static::query()->get();
    }

    public static function find(int|string $id): ?static
    {
        $instance = new static();
        $result = static::query()->find($id, $instance->getKeyName());
        return $result ?: null;
    }

    public static function findOrFail(int|string $id): static
    {
        $model = static::find($id);
        if (!$model) {
            throw new \RuntimeException(static::class . " with key {$id} not found");
        }
        return $model;
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    // ── HYDRATION ──────────────────────────────────────────

    public static function newFromBuilder(array $attributes): static
    {
        $model = new static();
        $model->attributes = $attributes;
        $model->exists = true;
        $model->syncOriginal();
        return $model;
    }

    // ── PERSISTENCE ───────────────────────────────────────

    public function save(): bool
    {
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!$this->exists) {
                $this->attributes['created_at'] = $now;
            }
            $this->attributes['updated_at'] = $now;
        }

        if ($this->exists) {
            if (!$this->isDirty()) {
                return true;
            }

            $dirty = $this->getDirty();
            unset($dirty[$this->primaryKey]);

            static::query()
                ->where($this->primaryKey, $this->getKey())
                ->update($dirty);

            $this->syncOriginal();
            return true;
        }

        // ── AUTO GENERATE KEY buat non-incrementing (misal UUID) ──
        if (!$this->incrementing && !isset($this->attributes[$this->primaryKey])) {
            $generated = $this->generateKeyValue();
            if ($generated !== null) {
                $this->attributes[$this->primaryKey] = $generated;
            }
        }

        $insertedId = static::query()->insert($this->attributes);

        if ($this->incrementing) {
            // auto-increment: ambil dari lastInsertId, cast sesuai keyType
            $this->attributes[$this->primaryKey] = $this->castKeyType($insertedId);
        }
        // kalau non-incrementing (UUID dll), value udah di-set manual di atas, ga perlu overwrite

        $this->exists = true;
        $this->syncOriginal();

        return true;
    }

    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        static::query()->where($this->primaryKey, $this->getKey())->delete();
        $this->exists = false;

        return true;
    }

    // ── RELATIONS ─────────────────────────────────────────

    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $foreignKey ??= $this->getForeignKeyName();
        $localKey ??= $this->getKeyName();

        return new HasOne($this, $related, $foreignKey, $localKey);
    }

    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $foreignKey ??= $this->getForeignKeyName();
        $localKey ??= $this->getKeyName();

        return new HasMany($this, $related, $foreignKey, $localKey);
    }

    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $relatedInstance = new $related();
        $foreignKey ??= $relatedInstance->getForeignKeyName();
        $ownerKey ??= $relatedInstance->getKeyName();

        return new BelongsTo($this, $related, $foreignKey, $ownerKey);
    }

    public function belongsToMany(
        string $related,
        ?string $pivotTable = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null
    ): BelongsToMany {
        $relatedInstance = new $related();

        $pivotTable ??= $this->getPivotTableName($relatedInstance);
        $foreignPivotKey ??= $this->getForeignKeyName();
        $relatedPivotKey ??= $relatedInstance->getForeignKeyName();

        return new BelongsToMany($this, $related, $pivotTable, $foreignPivotKey, $relatedPivotKey);
    }

    public function morphOne(string $related, string $morphName, ?string $localKey = null): MorphOne
    {
        $localKey ??= $this->getKeyName();
        return new MorphOne($this, $related, $morphName, $localKey);
    }

    public function morphMany(string $related, string $morphName, ?string $localKey = null): MorphMany
    {
        $localKey ??= $this->getKeyName();
        return new MorphMany($this, $related, $morphName, $localKey);
    }

    public function morphTo(?string $morphName = null): MorphTo
    {
        if ($morphName === null) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $morphName = $trace[1]['function'];
        }

        return new MorphTo($this, $morphName);
    }

    public function morphToMany(
        string $related,
        string $morphName,
        ?string $pivotTable = null,
        ?string $relatedPivotKey = null
    ): MorphToMany {
        $relatedInstance = new $related();

        $pivotTable ??= $morphName . 's';
        $relatedPivotKey ??= $relatedInstance->getForeignKeyName();

        return new MorphToMany($this, $related, $pivotTable, $morphName, $relatedPivotKey);
    }

    protected function getForeignKeyName(): string
    {
        $class = (new \ReflectionClass($this))->getShortName();
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
        return $snake . '_' . $this->primaryKey;
    }

    protected function getPivotTableName(Model $related): string
    {
        $tables = [$this->getTable(), $related->getTable()];
        sort($tables);
        return implode('_', $tables);
    }

    public function with(array $relations): ModelQueryBuilder
    {
        return static::query()->with($relations);
    }

    // ── SERIALIZATION ─────────────────────────────────────

    public function toArray(): array
    {
        $attributes = $this->attributes;

        $result = [];
        foreach (array_keys($attributes) as $key) {
            $result[$key] = $this->getAttribute($key);
        }

        foreach ($this->appends as $key) {
            $result[$key] = $this->getAttribute($key);
        }

        foreach ($this->relations as $key => $value) {
            $result[$key] = $value instanceof Model
                ? $value->toArray()
                : (is_array($value) ? array_map(fn($m) => $m instanceof Model ? $m->toArray() : $m, $value) : $value);
        }

        if (!empty($this->visible)) {
            $result = array_intersect_key($result, array_flip($this->visible));
        } elseif (!empty($this->hidden)) {
            $result = array_diff_key($result, array_flip($this->hidden));
        }

        // ── FORMAT DATETIME JADI ISO 8601 DENGAN TIMEZONE ──
        foreach ($result as $key => $value) {
            if ($value instanceof \DateTimeInterface) {

                $result[$key] = $value
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format('Y-m-d\TH:i:s\Z');
            }
        }

        return $result;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
