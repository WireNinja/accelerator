<?php

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Command;
use WireNinja\Accelerator\Services\AcceleratedUserService;

class FlushLastSeenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accelerator:flush-last-seen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush pending last_seen timestamps from Redis to the database.';

    /**
     * Execute the console command.
     */
    public function handle(AcceleratedUserService $service): int
    {
        $this->info('Flushing pending last_seen timestamps...');

        $service->flushPendingLastSeen();

        $this->info('Done!');

        return self::SUCCESS;
    }
}
