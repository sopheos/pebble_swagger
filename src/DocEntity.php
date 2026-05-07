<?php

namespace Pebble\Swagger;

class DocEntity
{
    public string $name;
    public array $value = [];

    public function __construct(string $name = '', array $value = [])
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function value(int $pos = 0): string
    {
        return $this->value[$pos] ?? '';
    }

    public function values(int $start = 0, ?int $len = null): array
    {
        if ($start === 0 && $len === null) {
            return $this->value;
        }
        return array_slice($this->value, $start, $len);
    }

    public function text(int $start = 0, ?int $len = null): string
    {
        return join(' ', $this->values($start, $len));
    }
}
