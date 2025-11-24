<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nik',
        'email',
        'password',
        'role',
        'jabatan',
        'skpd_id',
        'status',
        'signature_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
    }

    /**
     * Relasi ke SKPD
     */
    public function skpd()
    {
        return $this->belongsTo(Skpd::class);
    }

    /**
     * Relasi untuk asisten yang membawahi banyak SKPD
     */
    public function skpds()
    {
        return $this->hasMany(Skpd::class, 'asisten_id');
    }

    public function notaPersetujuan()
    {
        return $this->hasMany(NotaPersetujuan::class, 'approver_id');
    }

    public static $rules = [
        'nik' => 'required|digits:16|unique:users',
    ];
}
