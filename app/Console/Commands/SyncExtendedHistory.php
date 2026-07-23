<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GineeOrderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncExtendedHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:extended-history';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync historical order data up to 90 days back to populate extended fields';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(GineeOrderService $service)
    {
        $this->info('Starting background historical sync (up to 90 days)...');
        Log::info('SyncExtendedHistory command started.');

        // 90 days ago is the maximum safe window for ListOrder API
        $startDate = now()->subDays(90)->startOfDay();
        $endDate = now()->endOfDay();
        
        $this->info("Date Range: {$startDate->toDateString()} to {$endDate->toDateString()}");

        // We can chunk this by 7 days to avoid long running requests hitting limits
        $currentStart = $startDate->copy();
        
        $totalProcessed = 0;
        $totalNew = 0;
        $totalUpdated = 0;

        while ($currentStart->lt($endDate)) {
            $currentEnd = $currentStart->copy()->addDays(7)->endOfDay();
            if ($currentEnd->gt($endDate)) {
                $currentEnd = $endDate->copy();
            }

            $this->info("Syncing window: {$currentStart->toDateString()} - {$currentEnd->toDateString()}");
            Log::info("SyncExtendedHistory: Syncing window: {$currentStart->toDateString()} - {$currentEnd->toDateString()}");

            try {
                // Pass true for yieldToHotSync so we don't block the hot packing sync
                $result = $service->syncCreateDateRange($currentStart, $currentEnd, true);
                
                $totalProcessed += $result['totalProcessed'] ?? 0;
                $totalNew += $result['new'] ?? 0;
                $totalUpdated += $result['updated'] ?? 0;

                $this->info("Result for window: Processed: {$result['totalProcessed']}, Updated: {$result['updated']}, New: {$result['new']}");
            } catch (\Exception $e) {
                $this->error("Error syncing window: " . $e->getMessage());
                Log::error("SyncExtendedHistory Error: " . $e->getMessage());
            }

            // Sleep to avoid rate limits between chunks
            sleep(2);
            $currentStart = $currentEnd->copy()->addSecond();
        }

        $this->info('Finished historical sync!');
        $this->info("Grand Total - Processed: {$totalProcessed}, Updated: {$totalUpdated}, New: {$totalNew}");
        Log::info("SyncExtendedHistory command finished. Processed: {$totalProcessed}, Updated: {$totalUpdated}, New: {$totalNew}");

        return 0;
    }
}
