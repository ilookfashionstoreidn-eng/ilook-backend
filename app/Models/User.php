<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject {
    use Notifiable;
    use HasRoles; 
    
    protected $fillable = [
        'name', 'email', 'password','id_penjahit',  'invited_by_supervisor', 'foto'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    // Implementasi metode dari JWTSubject
    public function getJWTIdentifier() {
        return $this->getKey();
    }

    public function getJWTCustomClaims() {
        return [];
    }
    public function penjahit()
    {
        return $this->belongsTo(Penjahit::class, 'id_penjahit', 'id_penjahit');
    }
    public function spkChatInvites()
    {
        return $this->hasMany(SpkChatInvite::class, 'staff_id');
    }
    
}
