<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Catalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogService
{
    public function save(string $resource, array $data, int $actor, ?int $id = null)
    {
        return DB::transaction(function () use ($resource, $data, $actor, $id) {
            $class = Catalog::definition($resource)['model'];
            $model = $id ? $class::lockForUpdate()->findOrFail($id) : new $class;
            if ($resource === 'users') {
                // Serialize administrator changes so two users cannot remove the final admin.
                $adminRole = Role::where('name', 'Administrador')->lockForUpdate()->firstOrFail();
                if ($id === $actor && (! $data['active'] || (int) $data['role_id'] !== $adminRole->id)) {
                    throw ValidationException::withMessages(['active' => 'No puedes quitarte el acceso de administrador.']);
                }
                if ($id && $model->role_id === $adminRole->id && (! $data['active'] || (int) $data['role_id'] !== $adminRole->id) && User::where('role_id', $adminRole->id)->where('active', true)->count() <= 1) {
                    throw ValidationException::withMessages(['role_id' => 'Debe existir al menos un administrador activo.']);
                }
                if (empty($data['password'])) {
                    unset($data['password']);
                }
                $model->role_id = $data['role_id'];
                $model->active = $data['active'];
                unset($data['role_id'],$data['active']);
            }
            $model->fill($data);
            $model->save();
            AuditLog::create(['user_id' => $actor, 'action' => $id ? 'updated' : 'created', 'subject_type' => $resource, 'subject_id' => $model->id]);

            return $model;
        });
    }
}
