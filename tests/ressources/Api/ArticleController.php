<?php

namespace App\Api;

class ArticleController
{
    /**
     * oa-url /api/articles/{id}
     * oa-method get
     * oa-tags Articles Public
     * oa-scope admin
     * oa-summary Get an article
     * oa-desc First line
     * oa-desc Second line
     * oa-public
     * oa-path id integer:int64 Article id
     * oa-query fields array:string Fields to return
     * oa-query q string:/^[a-z]+$/
     * oa-required id
     * oa-code 404 Not found
     * oa-code 404 Or deleted
     * oa-res 200 UserResult The article
     */
    public function show() {}

    /**
     * oa-url /api/articles
     * oa-method post
     * oa-private adminToken
     * oa-json UserResult Article to create
     * oa-query ignored string
     * oa-code 201 Created
     * oa-code 400 Invalid
     * oa-res 400 ErrorResult
     */
    public function create() {}

    /**
     * oa-url /api/articles/{id}/image
     * oa-method put
     * oa-path id integer
     * oa-data file string:binary Image file
     * oa-data alt string
     * oa-required file
     * oa-res 200 Sub/PointResult[]
     */
    public function upload() {}

    /**
     * oa-url /api/articles/{id}
     * oa-method delete
     */
    public function delete() {}

    /**
     * Helper without any documentation tag.
     */
    public function helper() {}
}
