<?php

use Illuminate\Support\Facades\Broadcast;




Broadcast::channel('spk-chat-notification.{id}', function ($user, $id) {
    return true; // Semua user bisa akses semua SPK
});
