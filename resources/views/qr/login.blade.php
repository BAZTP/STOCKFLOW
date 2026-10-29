@extends('layouts.app')
@section('title', 'Acceso Móvil QR')

@section('content')
<div class="mx-auto max-w-xl">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <a class="text-sm text-blue-600 hover:underline" href="{{ route('dashboard') }}">← Volver al panel</a>
            <h1 class="mt-2 text-2xl font-bold">Acceso Móvil con Código QR</h1>
        </div>
    </div>

    <div class="card p-7 text-center">
        <div class="mx-auto mb-4 inline-flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600">
            <i data-lucide="smartphone" class="h-6 w-6"></i>
        </div>

        <h2 class="text-lg font-semibold text-slate-800">Escanea con la cámara de tu celular</h2>
        <p class="mt-2 text-sm text-slate-500">
            Inicia sesión automáticamente como <strong>{{ $user->name }}</strong> sin tener que ingresar correo ni contraseña.
        </p>

        <div class="my-6 flex justify-center">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <img src="{{ $qrDataUri }}" alt="Código QR de acceso" class="mx-auto h-64 w-64">
            </div>
        </div>

        <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-left text-xs text-blue-900">
            <p class="font-semibold flex items-center gap-1.5">
                <i data-lucide="wifi" class="h-4 w-4"></i>
                Requisito de red:
            </p>
            <p class="mt-1">
                Tu celular debe estar conectado a la <strong>misma red Wi-Fi</strong> que esta computadora.
            </p>
            <div class="mt-3 break-all font-mono text-[11px] text-blue-700 bg-white/70 p-2 rounded border border-blue-200">
                URL: <a href="{{ $qrUrl }}" target="_blank" class="hover:underline">{{ $qrUrl }}</a>
            </div>
        </div>

        @if(count($availableIps) > 1)
        <div class="mt-4 text-left">
            <p class="text-xs font-medium text-slate-600">IP seleccionada para el QR:</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($availableIps as $ip)
                    <a href="{{ route('qr.generate', ['ip' => $ip]) }}" 
                       class="text-xs px-3 py-1.5 rounded-lg border {{ $ip === $selectedIp ? 'bg-blue-600 text-white border-blue-600 font-semibold' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' }}">
                        {{ $ip }} {{ $ip === $selectedIp ? '(Activa)' : '' }}
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('products.create') }}" class="btn">
                <i data-lucide="plus-circle" class="h-4 w-4 mr-1 inline"></i>
                Ir a Agregar Producto (Foto)
            </a>
            <a href="{{ route('dashboard') }}" class="btn-secondary">
                Volver al panel
            </a>
        </div>
    </div>
</div>
@endsection
