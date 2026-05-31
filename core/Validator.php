<?php
declare(strict_types=1);

namespace Flint;

use Flint\Exceptions\ValidationException;

class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $data) {}

    /** Validate data against rules; returns validated subset or throws ValidationException. */
    public function validate(array $rules): array
    {
        $validated = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $nullable = in_array('nullable', $fieldRules, true);

            if ($nullable && ($value === null || $value === '')) {
                continue;
            }

            foreach ($fieldRules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }
                $this->applyRule($field, $value, $rule);
            }

            if (!isset($this->errors[$field])) {
                $validated[$field] = $value;
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return $validated;
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        if (str_starts_with($rule, 'min:')) {
            $n = (int) substr($rule, 4);
            if (is_numeric($value)) {
                if ((float) $value < $n) {
                    $this->addError($field, "The {$field} must be at least {$n}.");
                }
            } elseif (strlen((string) $value) < $n) {
                $this->addError($field, "The {$field} must be at least {$n} characters.");
            }
            return;
        }

        if (str_starts_with($rule, 'max:')) {
            $n = (int) substr($rule, 4);
            if (is_numeric($value)) {
                if ((float) $value > $n) {
                    $this->addError($field, "The {$field} may not be greater than {$n}.");
                }
            } elseif (strlen((string) $value) > $n) {
                $this->addError($field, "The {$field} may not be greater than {$n} characters.");
            }
            return;
        }

        if (str_starts_with($rule, 'in:')) {
            $options = explode(',', substr($rule, 3));
            if (!in_array((string) $value, $options, true)) {
                $list = implode(', ', $options);
                $this->addError($field, "The {$field} must be one of: {$list}.");
            }
            return;
        }

        if (str_starts_with($rule, 'unique:')) {
            [$table, $column] = explode(',', substr($rule, 7));
            $qb = new QueryBuilder(trim($table));
            $exists = $qb->where(trim($column), $value)->first();
            if ($exists !== null) {
                $this->addError($field, "The {$field} has already been taken.");
            }
            return;
        }

        match ($rule) {
            'required' => $value === null || $value === ''
                ? $this->addError($field, "The {$field} field is required.")
                : null,

            'string' => !is_string($value)
                ? $this->addError($field, "The {$field} must be a string.")
                : null,

            'int', 'integer' => !filter_var($value, FILTER_VALIDATE_INT)
                ? $this->addError($field, "The {$field} must be an integer.")
                : null,

            'numeric' => !is_numeric($value)
                ? $this->addError($field, "The {$field} must be numeric.")
                : null,

            'email' => !filter_var($value, FILTER_VALIDATE_EMAIL)
                ? $this->addError($field, "The {$field} must be a valid email address.")
                : null,

            'confirmed' => ($this->data["{$field}_confirmation"] ?? null) !== $value
                ? $this->addError($field, "The {$field} confirmation does not match.")
                : null,

            default => null,
        };
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
