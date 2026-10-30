<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Pengajuan;
use App\Models\PreferensiDashboard;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1) . Str::substr($initials, -1)
            : $initials;
    }

    /*
     |--------------------------------------------------------------------------
     | Peran pengguna (sesuai Tabel 3.3 dokumen patokan)
     |--------------------------------------------------------------------------
     */

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isOperator(): bool
    {
        return $this->role === UserRole::Operator;
    }

    public function isNasabah(): bool
    {
        return $this->role === UserRole::Nasabah;
    }

    /*
     |--------------------------------------------------------------------------
     | Relasi pengajuan (Fase 2)
     |--------------------------------------------------------------------------
     */

    /**
     * Pengajuan layanan milik pengguna ini (sebagai nasabah).
     *
     * @return HasMany<Pengajuan, $this>
     */
    public function pengajuans(): HasMany
    {
        return $this->hasMany(Pengajuan::class);
    }

    /*
     |--------------------------------------------------------------------------
     | Preferensi dashboard (Fase 5)
     |--------------------------------------------------------------------------
     */

    /**
     * Preferensi personalisasi dashboard tersimpan milik pengguna ini.
     *
     * @return HasOne<PreferensiDashboard, $this>
     */
    public function preferensiDashboard(): HasOne
    {
        return $this->hasOne(PreferensiDashboard::class);
    }

    /**
     * Preferensi dashboard efektif: record tersimpan bila ada, atau
     * instance baru berisi nilai bawaan model bila belum pernah
     * menyimpan (tanpa menulis ke basis data).
     */
    public function preferensiDashboardAtauBawaan(): PreferensiDashboard
    {
        return $this->preferensiDashboard ?? new PreferensiDashboard;
    }
}
