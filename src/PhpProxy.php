<?php

namespace Le\Dataplater;

use Closure;

class PhpProxy
{
    public function __get(string $name)
    {
        return Closure::fromCallable($name);
    }
}
