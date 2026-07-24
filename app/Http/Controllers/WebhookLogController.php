<?php

namespace App\Http\Controllers;

use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Carbon\Carbon;

class WebhookLogController extends Controller
{
    /**
     * Daftar log webhook dari Ginee untuk halaman monitoring /orderPacking
     */
    public function index(Request $request)
    {
        $date    = $request->query('date');
        $status  = $request->query('status');
        $search  = $request->query('search');
        $perPage = min((int) $request->query('per_page', 50), 200);

        if ($date === null || $date === '') {
            $date = Carbon::today()->toDateString();
        }

        $query = WebhookLog::with(['order' => function ($q) {
                $q->with('items')->select(
                    'id', 'order_number', 'tracking_number', 'platform',
                    'status', 'label_print_status', 'is_packed',
                    'shipping_deadline', 'customer_name', 'sku'
                );
            }])
            ->orderBy('created_at', 'desc');

        if ($date !== 'all') {
            $query->whereDate('created_at', $date);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ginee_order_id', 'like', "%{$search}%")
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('tracking_number', 'like', "%{$search}%")
                         ->orWhere('order_number', 'like', "%{$search}%");
                  })
                  ->orWhereIn('order_id',
                      \DB::table('order')
                          ->where('tracking_number', 'like', "%{$search}%")
                          ->orWhere('order_number', 'like', "%{$search}%")
                          ->pluck('id')
                  );
            });
        }

        $logs = $query->paginate($perPage);

        // Stats (sesuai filter tanggal jika bukan 'all')
        $base = WebhookLog::query();
        if ($date !== 'all') {
            $base->whereDate('created_at', $date);
        }

        $stats = [
            'total'     => (clone $base)->count(),
            'processed' => (clone $base)->where('status', 'processed')->count(),
            'failed'    => (clone $base)->where('status', 'failed')->count(),
            'received'  => (clone $base)->where('status', 'received')->count(),
        ];

        return response()->json([
            'logs'  => $logs,
            'stats' => $stats,
        ]);
    }
}
