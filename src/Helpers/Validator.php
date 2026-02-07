<?php

declare(strict_types=1);

namespace App\Helpers;

class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $ruleParam = $parts[1] ?? null;

        match ($ruleName) {
            'required'     => $this->validateRequired($field, $value),
            'string'       => $this->validateString($field, $value),
            'email'        => $this->validateEmail($field, $value),
            'min'          => $this->validateMin($field, $value, (int) $ruleParam),
            'max'          => $this->validateMax($field, $value, (int) $ruleParam),
            'date'         => $this->validateDate($field, $value),
            'datetime'     => $this->validateDatetime($field, $value),
            'password'     => $this->validatePassword($field, $value),
            'boolean'      => $this->validateBoolean($field, $value),
            'integer'      => $this->validateInteger($field, $value),
            default        => null,
        };
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function validateRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->addError($field, "{$field} is required.");
        }
    }

    private function validateString(string $field, mixed $value): void
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($field, "{$field} must be a string.");
        }
    }

    private function validateEmail(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, "{$field} must be a valid email address.");
        }
    }

    private function validateMin(string $field, mixed $value, int $min): void
    {
        if (is_string($value) && mb_strlen($value) < $min) {
            $this->addError($field, "{$field} must be at least {$min} characters.");
        }
    }

    private function validateMax(string $field, mixed $value, int $max): void
    {
        if (is_string($value) && mb_strlen($value) > $max) {
            $this->addError($field, "{$field} must not exceed {$max} characters.");
        }
    }

    private function validateDate(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $date = \DateTime::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $this->addError($field, "{$field} must be a valid date (YYYY-MM-DD).");
            }
        }
    }

    private function validateDatetime(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
            if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
                $this->addError($field, "{$field} must be a valid datetime (YYYY-MM-DD HH:MM:SS).");
            }
        }
    }

    private function validatePassword(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (mb_strlen($value) < 8) {
            $this->addError($field, "{$field} must be at least 8 characters.");
        }
        if (!preg_match('/[A-Z]/', $value)) {
            $this->addError($field, "{$field} must contain at least one uppercase letter.");
        }
        if (!preg_match('/[a-z]/', $value)) {
            $this->addError($field, "{$field} must contain at least one lowercase letter.");
        }
        if (!preg_match('/[0-9]/', $value)) {
            $this->addError($field, "{$field} must contain at least one number.");
        }
        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $this->addError($field, "{$field} must contain at least one special character.");
        }
    }

    private function validateBoolean(string $field, mixed $value): void
    {
        if ($value !== null && !is_bool($value) && $value !== 0 && $value !== 1) {
            $this->addError($field, "{$field} must be a boolean.");
        }
    }

    private function validateInteger(string $field, mixed $value): void
    {
        if ($value !== null && !is_int($value)) {
            $this->addError($field, "{$field} must be an integer.");
        }
    }
}
