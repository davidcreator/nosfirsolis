<?php

namespace System\Engine;

abstract class Model
{
    public function __construct(protected Registry $registry)
    {
    }

    public function __get(string $key): mixed
    {
        return $this->registry->get($key);
    }
}
