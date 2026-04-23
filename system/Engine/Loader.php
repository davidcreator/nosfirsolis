<?php

namespace System\Engine;

class Loader
{
    public function __construct(private readonly Registry $registry, private readonly string $area)
    {
    }

    public function model(string $name, ?string $area = null): object
    {
        $targetArea = ucfirst(strtolower($area ?? $this->area));
        $modelClass = $targetArea . '\\Model\\' . $this->studly($name) . 'Model';

        if (!class_exists($modelClass)) {
            throw new \RuntimeException('Model nao encontrada: ' . $modelClass);
        }

        return new $modelClass($this->registry);
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', strtolower($value));

        return str_replace(' ', '', ucwords($value));
    }
}
