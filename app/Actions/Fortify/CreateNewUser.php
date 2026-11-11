<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->after($this->requireInvitation(...))->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }

    /**
     * When registration is by invitation, only accept people who have been invited.
     */
    private function requireInvitation(ValidatorInstance $validator): void
    {
        if (! TeamInvitation::required() || $validator->errors()->has('email')) {
            return;
        }

        $email = $validator->getData()['email'] ?? null;

        if (! is_string($email) || ! TeamInvitation::query()->pending()->sentTo($email)->exists()) {
            $validator->errors()->add('email', __('Registration is by invitation only. Ask an administrator to invite this email address.'));
        }
    }
}
