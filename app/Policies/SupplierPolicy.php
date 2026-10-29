<?php

namespace App\Policies;

class SupplierPolicy extends ResourcePolicy
{
    protected static function module(): string
    {
        return 'suppliers';
    }
}
