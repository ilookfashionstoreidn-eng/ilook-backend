<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'spk_id', 'chat_id',  'is_read', 'read_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function spk()
    {
        return $this->belongsTo(SpkCmt::class, 'spk_id', 'id_spk');
    }

    public function chat()
    {
        return $this->belongsTo(SpkChat::class, 'chat_id', 'id');
    }
    
}
