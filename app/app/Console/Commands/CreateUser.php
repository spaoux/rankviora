<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'rankviora:user';

    protected $description = 'Gizli parola istemi ile güvenli kullanıcı hesabı oluşturur';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Güvenli parola istemi için etkileşimli terminal gereklidir.');

            return self::FAILURE;
        }
        $name = $this->ask('Ad soyad');
        $email = strtolower(trim((string) $this->ask('E-posta')));
        $password = $this->secret('Parola (en az 12 karakter; büyük/küçük harf, sayı, sembol)');
        $confirmation = $this->secret('Parolayı tekrar girin');
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], ['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users,email', 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $this->info('Hesap oluşturuldu. Giriş yaptıktan sonra ilk workspace’inizi oluşturabilirsiniz.');

        return self::SUCCESS;
    }
}
