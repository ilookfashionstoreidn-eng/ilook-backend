<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SpkChatInvite;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function inviteStaff($spkId, $staffId)
    {
        $staff = User::where('id', $staffId)->whereHas('roles', function ($query) {
            $query->where('name', 'staff');
        })->first();

        if (!$staff) {
            return response()->json(['error' => 'Staff not found'], 404);
        }
        $existingInvite = SpkChatInvite::where('staff_id', $staffId)
                                    ->where('spk_id', $spkId)
                                    ->exists();

        if ($existingInvite) {
            return response()->json(['message' => 'Staff is already invited to this SPK'], 200);
        }

        // Simpan ke database
        SpkChatInvite::create([
            'staff_id' => $staffId,
            'spk_id' => $spkId
        ]);

        return response()->json(['message' => 'Staff successfully invited to SPK'], 201);
    }

    public function getStaffList($spkId)
    {
        $staffs = User::whereHas('roles', function ($query) {
            $query->where('name', 'staff');
        })
        ->whereDoesntHave('spkChatInvites', function ($query) use ($spkId) {
            $query->where('spk_id', $spkId);
        })
        ->get(['id', 'name']);
    
        return response()->json($staffs);
    }
    

}    
