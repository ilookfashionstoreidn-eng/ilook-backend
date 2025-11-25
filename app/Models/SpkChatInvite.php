<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkChatInvite extends Model
{
    use HasFactory;

    protected $fillable = ['staff_id', 'spk_id'];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function spk()
    {
        return $this->belongsTo(SpkCmt::class, 'spk_id');
    }
}
