<?php

namespace Novalites\Exception;

class ValidationException extends \RuntimeException
{
    protected array $errors;

    public function __construct(array $errors, string $message = 'Data tidak valid')
    {
        parent::__construct($message, 422);
        $this->errors = $errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
