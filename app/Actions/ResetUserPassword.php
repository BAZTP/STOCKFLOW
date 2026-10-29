<?php

namespace App\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function reset($user, array $input): void
    {
        Validator::make($input, ['password' => 'required|string|min:12|confirmed'])->validate();
        $user->forceFill(['password' => $input['password'], 'remember_token' => Str::random(60)])->save();
    }
}
