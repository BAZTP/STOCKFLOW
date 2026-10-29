<?php

namespace App\Policies;

class ProductPolicy extends ResourcePolicy
{
    protected static function module(): string
    {
        return 'products';
    }
}
