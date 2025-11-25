<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkChat extends Model
{
    use HasFactory;

    protected $fillable = ['id_spk','image_url','video_url','vn_url', 'user_id', 'message'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function spk()
    {
        return $this->belongsTo(SpkCmt::class, 'id_spk', 'id_spk'); // Sesuaikan jika perlu
    }

    public function readers()
{
    return $this->hasMany(ChatReader::class, 'chat_id', 'id');
}

    
}
