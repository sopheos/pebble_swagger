<?php

namespace App\Invalid;

class MissingUrl
{
    /**
     * oa-method get
     * oa-summary No url
     */
    public function index() {}
}

class UpperCaseMethod
{
    /**
     * oa-url /upper
     * oa-method GET
     */
    public function index() {}
}

class AccidentalTag
{
    /**
     * Talks about the Goa-trance festival.
     */
    public function index() {}
}

class UnknownResult
{
    /**
     * oa-url /unknown
     * oa-method get
     * oa-res 200 NopeResult
     */
    public function index() {}
}

class BackslashResult
{
    /**
     * oa-url /backslash
     * oa-method get
     * oa-res 200 Sub\PointResult
     */
    public function index() {}
}

class DuplicateA
{
    /**
     * oa-url /dup
     * oa-method get
     * oa-summary First
     */
    public function index() {}
}

class DuplicateB
{
    /**
     * oa-url /dup
     * oa-method get
     * oa-summary Second
     */
    public function index() {}
}
