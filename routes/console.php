<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('stockflow:admin {email} {--name=Administrador}', function () {
    $email = $this->argument('email');
    Validator::make(['email' => $email], ['email' => 'required|email'])->validate();
    if (User::where('email', $email)->exists()) {
        $this->error('El usuario ya existe; no se modificó.');

        return 1;
    }
    $password = $this->secret('Contraseña (mínimo 12 caracteres)');
    Validator::make(['password' => $password], ['password' => 'required|min:12'])->validate();
    $role = Role::where('name', 'Administrador')->firstOrFail();
    $user = new User(['name' => $this->option('name'), 'email' => $email, 'password' => $password]);
    $user->role_id = $role->id;
    $user->save();
    $this->info('Administrador creado.');
})->purpose('Crear el primer administrador sin guardar contraseñas en código');

Artisan::command('stockflow:demo', function () {
    $this->call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
    $this->info('Datos demo disponibles. No se creó ninguna cuenta con contraseña pública.');
})->purpose('Cargar datos ficticios una sola vez, sin duplicar operaciones');
