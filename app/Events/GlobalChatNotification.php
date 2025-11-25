<?php

namespace App\Events;

use App\Models\SpkChat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\SpkChatInvite;

class GlobalChatNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;
    public $socketId;
    public $allowedUsers;

    public function __construct(SpkChat $chat, $allowedUsers, $socketId = null) {
        $this->chat = $chat->load('user');
        $this->socketId = $socketId;
        $this->allowedUsers = $allowedUsers;
    }
    

    public function broadcastOn()
    {
        return new Channel('spk-global-chat-notification');
    }

    public function broadcastAs()
    {
        return 'chat.notification';
    }

    public function broadcastWith()
    {
        return [
            'chat' => $this->chat,
            'allowed_users' => $this->allowedUsers, // Kirim daftar user yang boleh menerima notifikasi
        ];
    }
}
