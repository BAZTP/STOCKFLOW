<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ResourcePolicy
{
    public function viewAny(User $u): bool
    {
        return $u->permits(static::module().'.view');
    }

    public function view(User $u, Model $m): bool
    {
        return $this->viewAny($u);
    }

    public function create(User $u): bool
    {
        return $u->permits(static::module().'.manage');
    }

    public function update(User $u, Model $m): bool
    {
        return $this->create($u);
    }

    public function delete(User $u, Model $m): bool
    {
        return $this->create($u);
    }

    protected static function module(): string
    {
        return '';
    }
}
