@extends('layouts.app')
@section('title','Mi perfil')

@section('content')
<div class="max-w-2xl space-y-7">
    <div>
        <h1 class="text-2xl font-bold">Mi perfil</h1>
        <p class="mt-2 text-sm text-slate-500">Actualiza tu información y protege tu cuenta.</p>
    </div>

    <form class="card space-y-5 p-7" method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PUT')
        <label>Nombre
            <input name="name" value="{{ old('name',auth()->user()->name) }}" required>
        </label>
        <label>Correo electrónico
            <input type="email" name="email" value="{{ old('email',auth()->user()->email) }}" required>
        </label>
        <hr class="border-slate-100">
        <h2>Cambiar contraseña</h2>
        <p class="text-sm text-slate-500">Deja estos campos vacíos para mantener tu contraseña.</p>
        <label>Contraseña actual
            <input type="password" name="current_password" autocomplete="current-password">
        </label>
        <label>Nueva contraseña
            <input type="password" name="password" minlength="12" autocomplete="new-password">
        </label>
        <label>Confirmar contraseña
            <input type="password" name="password_confirmation" autocomplete="new-password">
        </label>
        <button class="btn">Guardar cambios</button>
    </form>

    <div class="card p-7">
        <h2 class="text-lg font-semibold flex items-center gap-2">
            <i data-lucide="qr-code" class="h-5 w-5 text-blue-600"></i>
            Acceso Móvil con Código QR
        </h2>
        <p class="mt-2 text-sm text-slate-500">
            Genera un código QR de acceso rápido para iniciar sesión en tu celular al instante y tomar fotos de los productos con la cámara.
        </p>
        <div class="mt-5">
            <a href="{{ route('qr.generate') }}" class="btn">
                <i data-lucide="smartphone" class="h-4 w-4 mr-1 inline"></i>
                Abrir Código QR para Celular
            </a>
        </div>
    </div>
</div>
@endsection
