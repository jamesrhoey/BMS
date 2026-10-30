<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var array<string, mixed> */
    private array $data;
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->addError($field, "{$label} is required.");
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label): self
    {
        $value = $this->stringValue($field);
        if ($value !== '' && mb_strlen($value, 'UTF-8') < $min) {
            $this->addError($field, "{$label} must be at least {$min} characters.");
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label): self
    {
        $value = $this->stringValue($field);
        if ($value !== '' && mb_strlen($value, 'UTF-8') > $max) {
            $this->addError($field, "{$label} must not exceed {$max} characters.");
        }
        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = $this->stringValue($field);
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, "{$label} must be a valid email address.");
        }
        return $this;
    }

    public function username(string $field, string $label): self
    {
        $value = $this->stringValue($field);
        if ($value !== '' && !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $value)) {
            $this->addError($field, "{$label} may only contain letters, numbers, dots, underscores, and hyphens (3-50 characters).");
        }
        return $this;
    }

    /**
     * @param list<string|int> $allowed
     */
    public function inList(string $field, array $allowed, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->addError($field, "Selected {$label} is invalid.");
        }
        return $this;
    }

    public function integer(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, "{$label} must be a valid number.");
        }
        return $this;
    }

    public function matches(string $field, string $otherField, string $label): self
    {
        $val1 = (string) ($this->data[$field] ?? '');
        $val2 = (string) ($this->data[$otherField] ?? '');
        if ($val1 !== $val2) {
            $this->addError($field, "{$label} confirmation does not match.");
        }
        return $this;
    }

    public function addError(string $field, string $message): self
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    private function stringValue(string $field): string
    {
        $val = $this->data[$field] ?? '';
        return is_scalar($val) ? trim((string) $val) : '';
    }
}

