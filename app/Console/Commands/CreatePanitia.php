<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreatePanitia extends Command
{
    protected $signature = 'panitia:create {name : Nama panitia} {username : Username untuk login}';

    protected $description = 'Buat akun panitia (atau reset password bila username sudah ada)';

    public function handle(): int
    {
        $name     = (string) $this->argument('name');
        $username = strtolower((string) $this->argument('username'));

        if (! preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
            $this->error('Username 3-30 karakter: huruf kecil, angka, titik, strip, garis bawah.');

            return self::FAILURE;
        }

        $user = User::where('username', $username)->first();
        if ($user && ! $this->confirm("$username sudah ada. Reset password?", false)) {
            return self::SUCCESS;
        }

        $password = (string) $this->secret('Password (min. 8 karakter)');
        if ($password !== (string) $this->secret('Ulangi password')) {
            $this->error('Password tidak sama.');

            return self::FAILURE;
        }
        $v = Validator::make(['password' => $password], ['password' => ['min:8']]);
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return self::FAILURE;
        }

        $user ??= new User;
        $user->forceFill([
            'name'     => $name,
            'username' => $username,
            // Kolom email bawaan Laravel wajib terisi & unik. Nilai ini hanya pengisi.
            'email'    => $user->email ?: $username . '@panitia.local',
            'password' => Hash::make($password),
        ])->save();

        $this->info("Akun panitia '$username' siap.");

        return self::SUCCESS;
    }
}
