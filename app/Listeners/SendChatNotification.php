<?php

namespace App\Listeners;

use App\Events\ChatSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\ChatNotification; // Jika Anda memiliki notifikasi untuk dikirim
use Illuminate\Support\Facades\Notification;

class SendChatNotification implements ShouldQueue
{
    

    public function handle(ChatSent $event)
    {    
        
    }
}
