<?php

namespace App\Policies;

class CustomerPolicy extends ResourcePolicy
{
    protected static function module(): string
    {
        return 'customers';
    }
}
