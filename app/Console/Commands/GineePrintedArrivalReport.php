<?php

namespace App\Console\Commands;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GineePrintedArrivalReport extends Command
{
    protected $signature = 'ginee:printed-arrival-report
        {--hours=6 : Ambil order PRINTED dalam N jam terakhir berdasarkan label_print_time}
        {--sample=10 : Jumlah sample order paling telat yang ditampilkan}';

    protected $description = 'Laporan jeda dari resi di-print sampai order tersedia di database lokal';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $sample = max(1, min(50, (int) $this->option('sample')));
        $since = now()->subHours($hours);

        $orders = Order::query()
            ->where('label_print_status', 'PRINTED')
            ->whereNotNull('label_print_time')
            ->where('label_print_time', '>=', $since)
            ->orderByDesc('label_print_time')
            ->get([
                'order_number',
                'tracking_number',
                'status',
                'label_print_time',
                'created_at',
                'updated_at',
            ]);

        $summary = [
            'alreadyInDbBeforePrint' => 0,
            'lag0to2' => 0,
            'lag3to5' => 0,
            'lag6to10' => 0,
            'lag11to30' => 0,
            'lagOver30' => 0,
            'missingCreatedAt' => 0,
        ];

        $positiveLags = [];
        $worstSamples = [];

        foreach ($orders as $order) {
            if (!$order->created_at) {
                $summary['missingCreatedAt']++;
                continue;
            }

            $labelPrintTime = Carbon::parse($order->label_print_time);
            $createdAt = Carbon::parse($order->created_at);
            $lagMinutes = $labelPrintTime->diffInMinutes($createdAt, false);

            if ($lagMinutes < 0) {
                $summary['alreadyInDbBeforePrint']++;
                continue;
            }

            $positiveLags[] = $lagMinutes;

            if ($lagMinutes <= 2) {
                $summary['lag0to2']++;
            } elseif ($lagMinutes <= 5) {
                $summary['lag3to5']++;
            } elseif ($lagMinutes <= 10) {
                $summary['lag6to10']++;
            } elseif ($lagMinutes <= 30) {
                $summary['lag11to30']++;
            } else {
                $summary['lagOver30']++;
            }

            $worstSamples[] = [
                'order_number' => $order->order_number,
                'tracking_number' => $order->tracking_number,
                'status' => $order->status,
                'label_print_time' => $labelPrintTime->format('Y-m-d H:i:s'),
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'lag_minutes' => $lagMinutes,
            ];
        }

        usort($worstSamples, static fn (array $left, array $right) => $right['lag_minutes'] <=> $left['lag_minutes']);

        $averageLag = !empty($positiveLags)
            ? round(array_sum($positiveLags) / count($positiveLags), 2)
            : 0;

        $maxLag = !empty($positiveLags)
            ? max($positiveLags)
            : 0;

        $this->info('Ginee Printed Arrival Report');
        $this->line('Waktu sekarang: ' . now()->format('Y-m-d H:i:s'));
        $this->line('Window PRINTED: ' . $since->format('Y-m-d H:i:s') . ' sampai ' . now()->format('Y-m-d H:i:s'));
        $this->line('Total order PRINTED dalam window: ' . $orders->count());
        $this->newLine();

        $this->info('Ringkasan');
        $this->line('Sudah ada di DB sebelum print: ' . $summary['alreadyInDbBeforePrint']);
        $this->line('Masuk ke DB 0-2 menit setelah print: ' . $summary['lag0to2']);
        $this->line('Masuk ke DB 3-5 menit setelah print: ' . $summary['lag3to5']);
        $this->line('Masuk ke DB 6-10 menit setelah print: ' . $summary['lag6to10']);
        $this->line('Masuk ke DB 11-30 menit setelah print: ' . $summary['lag11to30']);
        $this->line('Masuk ke DB >30 menit setelah print: ' . $summary['lagOver30']);
        $this->line('Rata-rata lag yang telat masuk: ' . $averageLag . ' menit');
        $this->line('Lag terlama: ' . $maxLag . ' menit');

        if ($summary['missingCreatedAt'] > 0) {
            $this->line('Order tanpa created_at: ' . $summary['missingCreatedAt']);
        }

        $this->newLine();
        $this->info('Sample Order Paling Telat');

        if (empty($worstSamples)) {
            $this->line('Tidak ada order yang masuk DB setelah print pada window ini.');

            return self::SUCCESS;
        }

        $this->table(
            ['Order', 'Tracking', 'Status', 'Print Time', 'Masuk DB', 'Lag Menit'],
            array_map(
                static fn (array $row) => [
                    $row['order_number'],
                    $row['tracking_number'],
                    $row['status'],
                    $row['label_print_time'],
                    $row['created_at'],
                    $row['lag_minutes'],
                ],
                array_slice($worstSamples, 0, $sample)
            )
        );

        return self::SUCCESS;
    }
}
