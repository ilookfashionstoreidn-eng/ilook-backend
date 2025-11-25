<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Models\SpkChat;

class ChatSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;

    // Inisialisasi event dengan chat
    public function __construct(SpkChat $chat)
    {
        $this->chat = $chat->load('user');
    }

    // Tentukan channel yang digunakan untuk broadcast
    public function broadcastOn()
    {
        return new Channel('spk-chat.' . $this->chat->id_spk);
    }

    // Tentukan nama event broadcast
    public function broadcastAs()
    {
        return 'chat.sent';
    }
}
