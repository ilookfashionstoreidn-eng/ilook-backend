<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatReader extends Model
{
    use HasFactory;

    protected $fillable = ['chat_id', 'user_id', 'read_at'];



    public function chat()
    {
        return $this->belongsTo(SpkChat::class);
    }
    public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}

}