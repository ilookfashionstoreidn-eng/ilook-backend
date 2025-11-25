<?php

namespace App\Http\Controllers;

use App\Events\ChatNotification;
use App\Events\ChatSent;
use App\Events\GlobalChatNotification;
use App\Models\SpkChat;
use App\Models\SpkCmt;
use App\Models\SpkChatInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\Models\Notification;
use App\Models\ChatReader;

class SpkChatController extends Controller
{
    public function index($spkId)
    {
        $user = auth()->user();
    
        if ($user->hasRole('staff')) {
            $isInvited = SpkChatInvite::where('staff_id', $user->id)
                                      ->where('spk_id', $spkId)
                                      ->exists();
    
            if (!$isInvited) {
                return response()->json(['error' => 'Access denied. Staff must be invited to this SPK.'], 403);
            }
        }
    
        // Ambil semua chat dalam SPK ini
        $chats = SpkChat::where('id_spk', $spkId)
        ->with(['user', 'readers.user:id,name']) // Ambil juga siapa yang sudah baca
        ->get();
    
        // Tandai semua chat sebagai "dibaca" oleh user
        foreach ($chats as $chat) {
            ChatReader::updateOrCreate(
                ['chat_id' => $chat->id, 'user_id' => $user->id],
                ['read_at' => now()]
            );
        }
    
        return response()->json($chats);
    }
    

    public function sendMessage(Request $request){
        $user = auth()->user();
        
        // Validasi request
        $request->validate([
            'id_spk' => 'required|exists:spk_cmt,id_spk',
            'message' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'video' => 'nullable|file|mimes:mp4,mov,mkv,avi,wmv|max:15240',
            'vn' => 'nullable|file|mimes:mp3,wav,ogg,webm|max:10240',
        ]);
        
        // Pastikan minimal salah satu ada
        if (!$request->has('message') && !$request->hasFile('image') && !$request->hasFile('video') && !$request->hasFile('vn')) {

            return response()->json(['error' => 'Either message, image, or video is required.'], 422);
        }
        

        // Jika user adalah staff, cek apakah diundang ke SPK
        if ($user->hasRole('staff')) {
            $isInvited = SpkChatInvite::where('staff_id', $user->id)
                                    ->where('spk_id', $request->id_spk)
                                    ->exists();

            if (!$isInvited) {
                return response()->json(['error' => 'Access denied. Staff must be invited to this SPK.'], 403);
            }
        }
        $allowedUsers = User::role(['supervisor', 'owner', 'super-admin','penjahit'])
        ->pluck('id')
        ->toArray();

        // Ambil juga staff yang diundang ke SPK ini
        $invitedStaff = SpkChatInvite::where('spk_id', $request->id_spk)
                            ->pluck('staff_id')
                            ->toArray();

        // Gabungkan dan pastikan unique
        $allowedUsers = array_unique(array_merge($allowedUsers, $invitedStaff));



            // Buat pesan baru
            $chat = new SpkChat();
            $chat->message = $request->message ?? null; // Bisa kosong jika hanya gambar
            $chat->id_spk = $request->id_spk;
            $chat->user_id = $user->id;

            $chat->message = $request->message ?? '';
            // Jika ada gambar, simpan dan ambil URL
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('chat_images', 'public');
                $chat->image_url = URL::to(Storage::url($path));
            }
            if ($request->hasFile('video')) {
                $path = $request->file('video')->store('chat_videos', 'public');
                $chat->video_url = URL::to(Storage::url($path)); // Lebih simpel dan konsisten
            }
            if ($request->hasFile('vn')) {
                $path = $request->file('vn')->store('chat_vn', 'public');
                $chat->vn_url = URL::to(Storage::url($path)); // Lebih simpel dan konsisten
            }

            $chat->save();
            // Simpan notifikasi untuk semua user yang berhak
            foreach ($allowedUsers as $receiverId) {
                if ($receiverId != $user->id) { // Jangan kirim notif ke pengirim sendiri
                    \Log::info('Membuat notifikasi untuk user_id: ' . $receiverId);

                    Notification::create([
                        'user_id' => $receiverId,
                        'spk_id' => $request->id_spk,
                        'chat_id' => $chat->id,
                        'message' => $chat->message ?? '[Media Message]',
                        'is_read' => false,
                    ]);

                    \Log::info('Notifikasi berhasil dibuat untuk user_id: ' . $receiverId);
                }
            }


            // Kirim notifikasi real-time jika diperlukan
            if (in_array($user->id, $allowedUsers)) {
                broadcast(new GlobalChatNotification($chat, $allowedUsers))->toOthers();
                \Log::info('GlobalChatNotification broadcasted', ['chat' => $chat]);
            } else {
                \Log::info('User tidak memiliki izin untuk menerima notifikasi', ['user_id' => $user->id]);
            }
                        
          \Log::info('Socket ID from request:', [$request->header('X-Socket-ID')]);

                // Kembalikan response JSON
            return response()->json([
                'success' => true,
                'message' => 'Chat berhasil dikirim',
                'data' => $chat
            ], 201);
    }

    public function checkInvitation($spkId)
{
    $user = auth()->user();

    // Hanya role staff yang perlu dicek
    if ($user->hasRole('staff')) {
        $isInvited = SpkChatInvite::where('staff_id', $user->id)
                                  ->where('spk_id', $spkId)
                                  ->exists();

        if ($isInvited) {
            return response()->json(['invited' => true, 'message' => 'Staff is invited to this SPK'], 200);
        } else {
            return response()->json(['invited' => false, 'message' => 'Staff is not invited to this SPK'], 403);
        }
    }

    // Jika bukan staff, otomatis bisa mengakses
    return response()->json(['invited' => true, 'message' => 'Role does not require invitation'], 200);
}

public function markAsRead($spkId)
{
    $userId = auth()->id();

    // Ambil semua pesan dalam SPK ini
    $unreadChats = SpkChat::where('id_spk', $spkId)->pluck('id');

    foreach ($unreadChats as $chatId) {
        ChatReader::updateOrCreate(
            ['chat_id' => $chatId, 'user_id' => $userId],
            ['read_at' => now()]
        );
    }

    return response()->json(['message' => 'All messages in SPK marked as read']);
}


public function getChatReaders($spkId)
{
    $readers = ChatReader::whereIn('chat_id', function ($query) use ($spkId) {
        $query->select('id')->from('spk_chats')->where('id_spk', $spkId);
    })->with('user:id,name')->get();

    // Grouping berdasarkan chat_id
    $groupedReaders = $readers->groupBy('chat_id');

    return response()->json($groupedReaders);
}


    
}
