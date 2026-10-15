<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_secure_setup_creates_hashed_password(): void
    {
        $this->artisan('rankviora:user')
            ->expectsQuestion('Ad soyad', 'Workspace sahibi')
            ->expectsQuestion('E-posta', 'OWNER@example.com')
            ->expectsQuestion('Parola (en az 12 karakter; büyük/küçük harf, sayı, sembol)', 'Private!Setup827')
            ->expectsQuestion('Parolayı tekrar girin', 'Private!Setup827')
            ->assertSuccessful();

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Private!Setup827', $user->password));
        $this->assertNotSame('Private!Setup827', $user->password);
    }

    public function test_weak_password_does_not_create_account(): void
    {
        $this->artisan('rankviora:user')
            ->expectsQuestion('Ad soyad', 'Workspace sahibi')
            ->expectsQuestion('E-posta', 'owner@example.com')
            ->expectsQuestion('Parola (en az 12 karakter; büyük/küçük harf, sayı, sembol)', 'weak')
            ->expectsQuestion('Parolayı tekrar girin', 'weak')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
