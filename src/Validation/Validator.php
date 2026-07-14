<?php

namespace Novalites\Validation;

use Novalites\Database\Manager;
use Novalites\Exception\ValidationException;

class Validator
{
    protected array $data;
    protected array $rules;
    protected array $messages;
    protected array $attributes;
    protected array $errors = [];

    /**
     * Custom rule yang didaftarin lewat Validator::extend()
     */
    protected static array $customRules = [];
    protected static array $customMessages = [];

    public function __construct(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        $this->data = $data;
        $this->rules = $this->normalizeRules($rules);
        $this->messages = $messages;
        $this->attributes = $attributes;
    }

    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): static
    {
        return new static($data, $rules, $messages, $attributes);
    }

    /**
     * Daftarin custom rule. Contoh:
     *   Validator::extend('phone_id', function ($value) {
     *       return preg_match('/^08\d{8,11}$/', $value);
     *   }, ':attribute harus berupa nomor HP Indonesia yang valid.');
     */
    public static function extend(string $name, callable $callback, string $message = ':attribute tidak valid.'): void
    {
        self::$customRules[$name] = $callback;
        self::$customMessages[$name] = $message;
    }

    protected function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $field => $ruleSet) {
            $normalized[$field] = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
        }

        return $normalized;
    }

    public function fails(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleSet) {
            $this->validateField($field, $ruleSet);
        }

        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    protected function validateField(string $field, array $ruleSet): void
    {
        $value = $this->getValue($field);
        $isNullable = in_array('nullable', $ruleSet, true);
        $isPresent = $this->hasValue($field);

        // Kalau field nullable dan kosong, skip semua rule lain (kecuali emang eksplisit ada rule 'required')
        if ($isNullable && !$isPresent && !in_array('required', $ruleSet, true)) {
            return;
        }

        foreach ($ruleSet as $rule) {
            if ($rule === 'nullable') {
                continue;
            }

            [$ruleName, $parameters] = $this->parseRule($rule);

            // Skip rule lain kalau field kosong DAN bukan rule 'required' itu sendiri
            // (biar ga muncul 2 error sekaligus: "wajib diisi" + "harus email" buat field kosong)
            if (!$isPresent && $ruleName !== 'required' && !str_starts_with($ruleName, 'required_')) {
                continue;
            }

            $passed = $this->callRule($ruleName, $field, $value, $parameters);

            if (!$passed) {
                $this->addError($field, $ruleName, $parameters);
            }
        }
    }

    protected function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $paramString] = explode(':', $rule, 2);
            return [$name, explode(',', $paramString)];
        }

        return [$rule, []];
    }

    protected function callRule(string $ruleName, string $field, mixed $value, array $parameters): bool
    {
        // Custom rule terdaftar lewat extend()
        if (isset(self::$customRules[$ruleName])) {
            return (bool) call_user_func(self::$customRules[$ruleName], $value, $parameters, $this->data);
        }

        $method = 'validate' . $this->studly($ruleName);

        if (!method_exists($this, $method)) {
            throw new \InvalidArgumentException("Rule validasi '{$ruleName}' tidak dikenali.");
        }

        return $this->{$method}($field, $value, $parameters);
    }

    protected function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }

    // ---------- Built-in rules ----------

    protected function validateRequired(string $field, mixed $value, array $params): bool
    {
        if (is_null($value)) return false;
        if (is_string($value) && trim($value) === '') return false;
        if (is_array($value) && count($value) === 0) return false;

        return true;
    }

    protected function validateString(string $field, mixed $value, array $params): bool
    {
        return is_string($value);
    }

    protected function validateInteger(string $field, mixed $value, array $params): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateNumeric(string $field, mixed $value, array $params): bool
    {
        return is_numeric($value);
    }

    protected function validateBoolean(string $field, mixed $value, array $params): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    protected function validateArray(string $field, mixed $value, array $params): bool
    {
        return is_array($value);
    }

    protected function validateEmail(string $field, mixed $value, array $params): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateUrl(string $field, mixed $value, array $params): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function validateIp(string $field, mixed $value, array $params): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    protected function validateJson(string $field, mixed $value, array $params): bool
    {
        if (!is_string($value)) return false;
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function validateUuid(string $field, mixed $value, array $params): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $value
        );
    }

    protected function validateAlpha(string $field, mixed $value, array $params): bool
    {
        return (bool) preg_match('/^[\pL\pM]+$/u', (string) $value);
    }

    protected function validateAlphaNum(string $field, mixed $value, array $params): bool
    {
        return (bool) preg_match('/^[\pL\pM\pN]+$/u', (string) $value);
    }

    protected function validateAlphaDash(string $field, mixed $value, array $params): bool
    {
        return (bool) preg_match('/^[\pL\pM\pN_-]+$/u', (string) $value);
    }

    protected function validateMin(string $field, mixed $value, array $params): bool
    {
        $min = (float) $params[0];

        return match (true) {
            is_numeric($value) && !is_array($value) => (float) $value >= $min,
            is_array($value) => count($value) >= $min,
            default => mb_strlen((string) $value) >= $min,
        };
    }

    protected function validateMax(string $field, mixed $value, array $params): bool
    {
        $max = (float) $params[0];

        return match (true) {
            is_numeric($value) && !is_array($value) => (float) $value <= $max,
            is_array($value) => count($value) <= $max,
            default => mb_strlen((string) $value) <= $max,
        };
    }

    protected function validateBetween(string $field, mixed $value, array $params): bool
    {
        [$min, $max] = array_map('floatval', $params);

        $size = match (true) {
            is_numeric($value) && !is_array($value) => (float) $value,
            is_array($value) => count($value),
            default => mb_strlen((string) $value),
        };

        return $size >= $min && $size <= $max;
    }

    protected function validateSize(string $field, mixed $value, array $params): bool
    {
        $size = (float) $params[0];

        $actual = match (true) {
            is_numeric($value) && !is_array($value) => (float) $value,
            is_array($value) => count($value),
            default => mb_strlen((string) $value),
        };

        return $actual == $size;
    }

    protected function validateDigits(string $field, mixed $value, array $params): bool
    {
        return (bool) preg_match('/^\d+$/', (string) $value) && strlen((string) $value) == $params[0];
    }

    protected function validateIn(string $field, mixed $value, array $params): bool
    {
        return in_array((string) $value, $params, true);
    }

    protected function validateNotIn(string $field, mixed $value, array $params): bool
    {
        return !in_array((string) $value, $params, true);
    }

    protected function validateRegex(string $field, mixed $value, array $params): bool
    {
        return (bool) preg_match($params[0], (string) $value);
    }

    protected function validateDate(string $field, mixed $value, array $params): bool
    {
        return strtotime((string) $value) !== false;
    }

    protected function validateDateFormat(string $field, mixed $value, array $params): bool
    {
        $format = $params[0];
        $date = \DateTime::createFromFormat($format, (string) $value);
        return $date !== false && $date->format($format) === $value;
    }

    protected function validateAfter(string $field, mixed $value, array $params): bool
    {
        return strtotime((string) $value) > strtotime($params[0]);
    }

    protected function validateBefore(string $field, mixed $value, array $params): bool
    {
        return strtotime((string) $value) < strtotime($params[0]);
    }

    protected function validateConfirmed(string $field, mixed $value, array $params): bool
    {
        $confirmField = $field . '_confirmation';
        return $this->getValue($confirmField) === $value;
    }

    protected function validateSame(string $field, mixed $value, array $params): bool
    {
        return $this->getValue($params[0]) === $value;
    }

    protected function validateDifferent(string $field, mixed $value, array $params): bool
    {
        return $this->getValue($params[0]) !== $value;
    }

    protected function validateRequiredIf(string $field, mixed $value, array $params): bool
    {
        [$otherField, $otherValue] = $params;

        if ((string) $this->getValue($otherField) === (string) $otherValue) {
            return $this->validateRequired($field, $value, []);
        }

        return true;
    }

    protected function validateRequiredWith(string $field, mixed $value, array $params): bool
    {
        foreach ($params as $otherField) {
            if ($this->hasValue($otherField)) {
                return $this->validateRequired($field, $value, []);
            }
        }
        return true;
    }

    /**
     * unique:table,column,ignoreId,idColumn
     * Contoh: 'email' => 'unique:users,email'
     *         'email' => 'unique:users,email,5'  <- kecualikan ID 5 (buat update)
     */
    protected function validateUnique(string $field, mixed $value, array $params): bool
    {
        $table = $params[0];
        $column = $params[1] ?? $field;
        $ignoreId = $params[2] ?? null;
        $idColumn = $params[3] ?? 'id';

        $query = Manager::table($table)->where($column, $value);

        if ($ignoreId !== null) {
            $query->where($idColumn, '!=', $ignoreId);
        }

        return $query->count() === 0;
    }

    /**
     * exists:table,column
     * Contoh: 'category_id' => 'exists:categories,id'
     */
    protected function validateExists(string $field, mixed $value, array $params): bool
    {
        $table = $params[0];
        $column = $params[1] ?? $field;

        return Manager::table($table)->where($column, $value)->exists();
    }

    // ---------- Helper ----------

    protected function hasValue(string $field): bool
    {
        return array_key_exists($field, $this->data) && $this->data[$field] !== null && $this->data[$field] !== '';
    }

    protected function getValue(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    protected function addError(string $field, string $rule, array $params): void
    {
        $this->errors[$field][] = $this->formatMessage($field, $rule, $params);
    }

    protected function formatMessage(string $field, string $rule, array $params): string
    {
        $key = "{$field}.{$rule}";

        $template = $this->messages[$key]
            ?? $this->messages[$rule]
            ?? self::$customMessages[$rule]
            ?? $this->defaultMessage($rule);

        $attribute = $this->attributes[$field] ?? str_replace('_', ' ', $field);

        $replacements = [':attribute' => $attribute];

        foreach ($params as $i => $param) {
            $replacements[":param{$i}"] = $param;
        }
        if (isset($params[0])) $replacements[':min'] = $params[0];
        if (isset($params[1])) $replacements[':max'] = $params[1];
        if (isset($params[0])) $replacements[':values'] = implode(', ', $params);

        return strtr($template, $replacements);
    }

    protected function defaultMessage(string $rule): string
    {
        return match ($rule) {
            'required'         => ':attribute wajib diisi.',
            'string'           => ':attribute harus berupa teks.',
            'integer'          => ':attribute harus berupa angka bulat.',
            'numeric'          => ':attribute harus berupa angka.',
            'boolean'          => ':attribute harus berupa true/false.',
            'array'            => ':attribute harus berupa array.',
            'email'            => ':attribute harus berupa alamat email yang valid.',
            'url'              => ':attribute harus berupa URL yang valid.',
            'ip'               => ':attribute harus berupa alamat IP yang valid.',
            'json'             => ':attribute harus berupa JSON yang valid.',
            'uuid'             => ':attribute harus berupa UUID yang valid.',
            'alpha'            => ':attribute hanya boleh berisi huruf.',
            'alpha_num'        => ':attribute hanya boleh berisi huruf dan angka.',
            'alpha_dash'       => ':attribute hanya boleh berisi huruf, angka, strip, dan underscore.',
            'min'              => ':attribute minimal :min.',
            'max'              => ':attribute maksimal :max.',
            'between'          => ':attribute harus di antara :min dan :max.',
            'size'             => ':attribute harus tepat berukuran :param0.',
            'digits'           => ':attribute harus terdiri dari :param0 digit.',
            'in'               => ':attribute yang dipilih tidak valid. Pilihan: :values.',
            'not_in'           => ':attribute yang dipilih tidak valid.',
            'regex'            => 'Format :attribute tidak valid.',
            'date'             => ':attribute harus berupa tanggal yang valid.',
            'date_format'      => 'Format :attribute tidak sesuai.',
            'after'            => ':attribute harus setelah :param0.',
            'before'           => ':attribute harus sebelum :param0.',
            'confirmed'        => 'Konfirmasi :attribute tidak cocok.',
            'same'             => ':attribute harus sama dengan :param0.',
            'different'        => ':attribute harus berbeda dengan :param0.',
            'unique'           => ':attribute sudah digunakan.',
            'exists'           => ':attribute yang dipilih tidak valid.',
            'required_if'      => ':attribute wajib diisi.',
            'required_with'    => ':attribute wajib diisi.',
            default            => ':attribute tidak valid.',
        };
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }

    /**
     * Return data yang cuma field-nya ada di $rules (mirip $request->validated() Laravel).
     */
    public function validated(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        return array_intersect_key($this->data, $this->rules);
    }
}
