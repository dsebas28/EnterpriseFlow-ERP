<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;

final class CreateUser
{
    /**
     * @param  bool  $emailVerified  True when the address was already proven
     *                               (e.g. the user arrived through an emailed invitation).
     */
    public function handle(string $name, string $email, string $password, bool $emailVerified = false): User
    {
        $user = new User([
            'name' => $name,
            'email' => Str::lower($email),
            'password' => $password, // hashed by the model cast
        ]);

        if ($emailVerified) {
            $user->email_verified_at = now();
        }

        $user->save();

        event(new Registered($user));

        return $user;
    }
}
