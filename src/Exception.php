<?php

namespace Pebble\Swagger;

class Exception extends \Exception
{
    public static function create(...$messages)
    {
        return new static(join(' ', $messages));
    }
}
