<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaLampiranSignature extends Model
{
    use HasFactory;

    protected $table = 'nota_lampiran_signatures';

    protected $fillable = [
        'nota_lampiran_id',
        'user_id',
        'path',
    ];

    public function lampiran()
    {
        return $this->belongsTo(NotaLampiran::class, 'nota_lampiran_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

