<?php

namespace App\Schemas;

/**
 * oa-desc A misc schema
 * oa-desc on two lines
 * oa-field code string:/^[a-z]+$/ Code
 * oa-field time string:/^[0-9]{2}:[0-9]{2}$/
 * oa-field count integer:int32 Number of 0 items
 * oa-field label  string   Label|second line
 * oa-field tags array:string:email
 * oa-field ids array
 * oa-field when string:date-time
 * oa-required code unknown
 */
class Misc {}

/**
 * A plain docblock without any tag.
 */
class NoTags {}

/**
 * oa-desc Only a description
 */
class NoProps {}

/**
 * oa-field id integer
 * oa-refs children Node
 */
class Node {}

/**
 * oa-ref user UserResult
 */
class CrossNamespace {}

/**
 * oa-ref child Misc
 * oa-refs items NoTags
 */
class MissingRef {}
