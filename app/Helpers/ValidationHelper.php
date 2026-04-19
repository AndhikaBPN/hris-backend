<?php

class ValidationHelper
{
    private array $errors = [];

    // ----------------------------------------------------------------
    // Validate required fields
    // ----------------------------------------------------------------
    public function required(array $data, array $fields): self
    {
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $this->errors[$field] = "{$field} is required";
            }
        }
        return $this;
    }

    // ----------------------------------------------------------------
    // Validate email format
    // ----------------------------------------------------------------
    public function email(array $data, string $field): self
    {
        if (!empty($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$field} must be a valid email";
        }
        return $this;
    }

    // ----------------------------------------------------------------
    // Validate minimum string length
    // ----------------------------------------------------------------
    public function minLength(array $data, string $field, int $min): self
    {
        if (!empty($data[$field]) && strlen($data[$field]) < $min) {
            $this->errors[$field] = "{$field} must be at least {$min} characters";
        }
        return $this;
    }

    // ----------------------------------------------------------------
    // Check & return errors
    // ----------------------------------------------------------------
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
