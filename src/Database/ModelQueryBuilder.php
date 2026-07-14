<?php

namespace Novalites\Database;

use Novalites\Database\Relations\Relation;
use Novalites\Support\Collection;
use PDO;

class ModelQueryBuilder extends QueryBuilder
{
    protected string $modelClass;
    protected array $eagerLoad = [];

    public function __construct(PDO $pdo, string $table, string $modelClass)
    {
        parent::__construct($pdo, $table);
        $this->modelClass = $modelClass;
    }

    public function with(array $relations): static
    {
        $this->eagerLoad = array_merge($this->eagerLoad, $relations);
        return $this;
    }



    public function get(): Collection
    {
        $rows = parent::get()->all(); // ambil array mentah dari Collection parent
        $models = array_map(fn($row) => $this->modelClass::newFromBuilder($row), $rows);

        if (!empty($this->eagerLoad)) {
            $tree = $this->buildEagerTree($this->eagerLoad);
            $this->loadEagerRecursive($models, $tree);
        }

        return new Collection($models);
    }

    public function first(): mixed
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? false;
    }

    public function find(int|string $id, string $primaryKey = 'id'): mixed
    {
        $primaryKey ??= (new $this->modelClass())->getKeyName();
        return $this->where($primaryKey, '=', $id)->first();
    }

    protected function loadEager(array $models): void
    {
        if (empty($models)) {
            return;
        }

        foreach ($this->eagerLoad as $relationName) {
            /** @var Model $sample */
            $sample = $models[0];

            if (!method_exists($sample, $relationName)) {
                continue;
            }

            foreach ($models as $model) {
                $relation = $model->$relationName();
                $result = $relation->getResults();

                // set langsung ke properti relations biar ga query ulang tiap getAttribute
                $ref = new \ReflectionProperty($model, 'relations');
                $ref->setAccessible(true);
                $current = $ref->getValue($model);
                $current[$relationName] = $result;
                $ref->setValue($model, $current);
            }
        }
    }

    protected function buildEagerTree(array $relations): array
    {
        $tree = [];

        foreach ($relations as $path) {
            $segments = explode('.', $path);
            $this->addToTree($tree, $segments);
        }

        return $tree;
    }

    protected function addToTree(array &$tree, array $segments): void
    {
        $current = array_shift($segments);

        if (!isset($tree[$current])) {
            $tree[$current] = ['_children' => []];
        }

        if (!empty($segments)) {
            $this->addToTree($tree[$current]['_children'], $segments);
        }
    }

    // ── RECURSIVE EAGER LOADER ──────────────────────────────

    /**
     * Load relasi level ini buat semua $models, lalu rekursif turun
     * ke _children pakai kumpulan model hasil relasi tadi sebagai parent baru.
     */
    protected function loadEagerRecursive(array $models, array $tree): void
    {
        if (empty($models)) {
            return;
        }

        foreach ($tree as $relationName => $node) {
            $sample = $models[0];

            if (!method_exists($sample, $relationName)) {
                continue; // skip diem-diem kalau nama relasi typo/ga ada, biar ga fatal error
            }

            $flatRelatedModels = [];

            foreach ($models as $model) {
                $relation = $model->$relationName();

                if (!$relation instanceof Relation) {
                    continue;
                }

                $result = $relation->getResults();

                // set langsung ke property protected $relations via reflection,
                // biar getAttribute() ga query ulang pas diakses kayak property
                $ref = new \ReflectionProperty($model, 'relations');
                $ref->setAccessible(true);
                $current = $ref->getValue($model);
                $current[$relationName] = $result;
                $ref->setValue($model, $current);

                if ($result === null) {
                    continue;
                }

                // kumpulin semua model hasil (baik single Model atau array of Model)
                // buat jadi "parent" di level nested berikutnya
                if (is_array($result)) {
                    array_push($flatRelatedModels, ...$result);
                } else {
                    $flatRelatedModels[] = $result;
                }
            }

            // rekursif ke child relation kalau ada, misal 'comments' di dalam 'posts.comments'
            if (!empty($node['_children'])) {
                $this->loadEagerRecursive($flatRelatedModels, $node['_children']);
            }
        }
    }
}
