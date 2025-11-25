<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use App\Models\SpkChat;
use App\Models\SpkChatInvite;

class ChatNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;
    public $socketId; 
    public $allowedUsers;

    // app/Events/ChatNotification.php
    public function __construct(SpkChat $chat, $socketId = null)
    {
        $this->chat = $chat->load('user'); 
        $this->socketId = $socketId; 
        
        // Ambil semua user yang boleh dapat notifikasi
        $this->allowedUsers = SpkChatInvite::where('spk_id', $chat->id_spk)
                                          ->pluck('staff_id') // Ambil ID staff yang diundang
                                          ->toArray();
    }

    public function broadcastOn()
{
    \Log::info("Broadcasting chat notification for SPK: " . $this->chat->id_spk);
    return new Channel('spk-chat-notification.' . $this->chat->id_spk);
}


    public function broadcastAs()
 {
        return 'chat.notification';
 }
 public function broadcastWith()
{
    return [
        'chat' => [
            'id' => $this->chat->id,
            'user_id' => $this->chat->id_user, // Pastikan `id_user` diambil dari model SpkChat
            'spk_id' => $this->chat->id_spk,  // Pastikan `id_spk` juga dikirim
            'message' => $this->chat->message ?? '📩 Pesan baru diterima',
            'time' => $this->chat->created_at->toDateTimeString(),
        ],
        'allowed_users' => $this->allowedUsers,
    ];
}


}
