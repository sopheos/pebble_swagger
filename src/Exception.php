<?php

namespace Pebble\Swagger;

class Exception extends \Exception
{
    public static function create(string ...$messages)
    {
        return new static(join(' ', $messages));
    }
}
