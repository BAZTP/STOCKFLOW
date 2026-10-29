<?php

namespace App\Policies;

class UserPolicy extends ResourcePolicy
{
    protected static function module(): string
    {
        return 'users';
    }
}
