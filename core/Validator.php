<?php

class Validator
{
    private array $errors = [];

    public function required(array $data, string $field, string $label): self
    {
        if (trim((string)($data[$field] ?? '')) === '') {
            $this->errors[$field] = "$label is required.";
        }
        return $this;
    }

    public function email(array $data, string $field, string $label): self
    {
        if (!empty($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "$label must be a valid email address.";
        }
        return $this;
    }

    public function minLength(array $data, string $field, int $min, string $label): self
    {
        if (!empty($data[$field]) && strlen((string)$data[$field]) < $min) {
            $this->errors[$field] = "$label must be at least $min characters.";
        }
        return $this;
    }

    public function maxLength(array $data, string $field, int $max, string $label): self
    {
        if (!empty($data[$field]) && strlen((string)$data[$field]) > $max) {
            $this->errors[$field] = "$label must be at most $max characters.";
        }
        return $this;
    }

    public function numeric(array $data, string $field, string $label): self
    {
        if (isset($data[$field]) && $data[$field] !== '' && !is_numeric($data[$field])) {
            $this->errors[$field] = "$label must be a number.";
        }
        return $this;
    }

    public function inArray(array $data, string $field, array $allowed, string $label): self
    {
        if (isset($data[$field]) && !in_array($data[$field], $allowed, true)) {
            $this->errors[$field] = "$label is invalid.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
