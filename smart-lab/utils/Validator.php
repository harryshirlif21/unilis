<?php
class Validator {
    private array $errors = [];
    public function required(string $f, mixed $v): self {
        if (empty($v)) $this->errors[$f] = "$f is required.";
        return $this;
    }
    public function email(string $f, mixed $v): self {
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) $this->errors[$f] = "Invalid email.";
        return $this;
    }
    public function minLength(string $f, mixed $v, int $min): self {
        if (strlen($v) < $min) $this->errors[$f] = "$f must be at least $min chars.";
        return $this;
    }
    public function passes(): bool  { return empty($this->errors); }
    public function errors(): array { return $this->errors; }
}
