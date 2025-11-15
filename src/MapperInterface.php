<?php

namespace Alecszaharia\Simmap;

interface MapperInterface
{
    public function map(object $source, object|string|null $target = null): object;
}