<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function getUnreadNotifications()
    {
        $userId = auth()->id();

        $unreadNotifications = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'notifications' => $unreadNotifications
        ]);
    }

    public function getNotifications()
    {
        $userId = auth()->id();

        $notifications = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'notifications' => $notifications
        ]);
    }
    public function markAsRead()
{
    $userId = auth()->id();

    Notification::where('user_id', $userId)
        ->where('is_read', false)
        ->update(['is_read' => true]);

    return response()->json(['message' => 'Notifikasi berhasil diperbarui']);
}

}
