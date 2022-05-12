<?php

namespace Le\Dataplater;

class PhpProxy
{

    public function __get(string $name)
    {
        return $name(...);
    }

}