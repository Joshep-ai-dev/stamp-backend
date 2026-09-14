<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateFoundingMember extends Command
{
    protected $signature = 'kroo:founding-member {email : Email used to sign in} {--name= : Passport name for a new member}';

    protected $description = 'Create the initial member and display their invitation code, or retrieve an existing member code';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));
        $validator = Validator::make(['email' => $email, 'name' => $name], [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:80'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        if ($name === '' && ! User::where('email', $email)->exists()) {
            $this->error('Provide --name for the new founding member.');

            return self::FAILURE;
        }

        $member = DB::transaction(function () use ($email, $name): User {
            $member = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                // Sign in through the existing email-code flow; no shared default password.
                'password' => Str::random(64),
            ]);
            $member = User::whereKey($member->id)->lockForUpdate()->firstOrFail();
            if (! $member->friend_code) {
                $member->update(['friend_code' => Str::random(36)]);
            }

            return $member;
        });

        $this->info('Member ready: '.$member->email);
        $this->line('Referral code: '.$member->friend_code);
        $this->line('Enter this code on the welcome screen, then tap Continue.');
        $this->line('To use this member account, sign in with the email above using an email verification code.');

        return self::SUCCESS;
    }
}
