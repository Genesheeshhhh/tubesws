<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{8,30}$/'],
            'city' => ['required', 'string', 'max:60'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'nomor telepon',
            'city' => 'kota',
            'password' => 'kata sandi',
        ];
    }

    public function createUser(): User
    {
        $user = User::create($this->safe()->only(['name', 'email', 'phone', 'city']) + [
            'password' => $this->string('password')->value(),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return $user;
    }
}
