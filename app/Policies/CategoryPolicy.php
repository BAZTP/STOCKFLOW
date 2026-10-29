<?php

namespace App\Policies;

class CategoryPolicy extends ResourcePolicy
{
    protected static function module(): string
    {
        return 'products';
    }
}
