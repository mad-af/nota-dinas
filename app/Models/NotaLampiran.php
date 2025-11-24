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
        'signature_user_ids',
    ];

    protected $casts = [
        'signature_user_ids' => 'array',
    ];

    public function addSignatureUserId($userId)
    {
        $signatures = $this->signature_user_ids ?? [];
        $uid = (string) $userId;
        if (! in_array($uid, $signatures, true)) {
            $signatures[] = $uid;
            $this->signature_user_ids = $signatures;
        }

        return $this;
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
