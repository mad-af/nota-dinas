<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaLampiran extends Model
{
    use HasFactory;

    protected $table = 'nota_lampirans';

    protected $fillable = [
        'nota_dinas_id',
        'nota_pengiriman_id',
        'nama_file',
        'path',
    ];

    public function signatures()
    {
        return $this->hasMany(NotaLampiranSignature::class);
    }

    public function addSignatureUserId($userId, $path)
    {
        $uid = (int) $userId;
        $exists = $this->signatures()->where('user_id', $uid)->exists();
        if (! $exists) {
            $this->signatures()->create([
                'user_id' => $uid,
                'path' => $path,
            ]);
        }

        return $this;
    }

    public function getSignatureUserIdsAttribute()
    {
        return $this->signatures()->pluck('user_id')->map(fn ($id) => (string) $id)->toArray();
    }

    /**
     * Relasi ke model NotaDinas
     */
    public function notaDinas()
    {
        return $this->belongsTo(NotaDinas::class);
    }

    public function pengirimans()
    {
        return $this->belongsToMany(NotaPengiriman::class, 'nota_pengiriman_lampiran', 'nota_lampiran_id', 'nota_pengiriman_id')
            ->withTimestamps();
    }
}
