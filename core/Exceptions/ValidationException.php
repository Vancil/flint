<?php
declare(strict_types=1);

namespace Flint\Exceptions;

class ValidationException extends \RuntimeException
{
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validation failed.');
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        return $this->errors;
    }
}
