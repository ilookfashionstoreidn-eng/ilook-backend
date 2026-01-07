<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SpkCmt;
use App\Models\LogStatusSpkCmt;
use Carbon\Carbon;
use App\Models\LogDeadline;

class AutoReleasePendingSpkCmt extends Command
{
    protected $signature = 'spk-cmt:auto-release-pending';

    protected $description = 'Mengubah status pending menjadi sudah_diambil jika pending_until sudah lewat';

   public function handle()
    {
        $today = now()->startOfDay();

        $spks = SpkCmt::where('status', 'pending')
            ->whereDate('pending_until', '<=', $today)
            ->get();

        foreach ($spks as $spk) {

            // 🔹 hitung durasi pending
            $pendingAt = Carbon::parse($spk->pending_at)->startOfDay();
            $pendingUntil = Carbon::parse($spk->pending_until)->startOfDay();
            $durasiPending = $pendingAt->diffInDays($pendingUntil) + 1;

            // 🔹 simpan deadline lama
            $deadlineLama = $spk->deadline;

            // 🔹 update deadline
            $deadlineBaru = Carbon::parse($spk->deadline)->addDays($durasiPending);

            // 🔹 update spk
            $spk->update([
                'status'        => 'sudah_diambil',
                'deadline'      => $deadlineBaru,
                'pending_at'    => null,
                'pending_until' => null,
            ]);

            // 🔹 log deadline
            LogDeadline::create([
                'id_spk'           => $spk->id_spk,
                'deadline_lama'    => $deadlineLama,
                'deadline_baru'    => $deadlineBaru,
                'tanggal_aktivitas' => now(),
                'keterangan'       => 'Deadline bertambah karena pending',
            ]);
        }

        $this->info("Processed {$spks->count()} SPK CMT.");
    }
}
