<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile');
    }

    public function update(ProfileRequest $r)
    {
        $data = $r->safe()->only(['name', 'email']);
        if ($r->filled('password')) {
            $data['password'] = $r->input('password');
        }$r->user()->update($data);
        if (isset($data['password'])) {
            $r->session()->regenerate();
        }

        return back()->with('status', 'Perfil actualizado.');
    }
}
