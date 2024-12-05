<?php

namespace App\Controllers;


class User
{
    /**
     * oa-url /api/points
     * oa-method get
     * oa-summary Detail d'un utilisateur
     * oa-private accessToken
     * oa-res 200 Sub/PointResult[]
     */
    public function points() {}

    /**
     * oa-url /api/organizer
     * oa-method get
     * oa-summary Detail d'un utilisateur
     * oa-private accessToken
     * oa-res 200 UserResult
     * oa-res 400 ErrorResult
     */
    public function detail() {}
}
