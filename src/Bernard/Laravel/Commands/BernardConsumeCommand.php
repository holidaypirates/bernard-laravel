<?php

namespace Bernard\Laravel\Commands;

use Illuminate\Console\Command;

class BernardConsumeCommand extends Command
{

    /**
     * @var string
     */
    protected $signature = 'bernard:consume
                            {queue : Name of the queue to consume.}
                            {--fail-queue= : Queue to re-order failed.}
                            {--max-retries=5: Max amount of retries.}
                            {--max-runtime= : Max time for consuming messages.}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consumes and works on Bernard queue.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $pullQueue = $this->argument('queue');
        $failQueue = $this->option('fail-queue');
        $maxRetries = $this->option('max-retries');
        $maxRuntime = $this->option('max-runtime');
        $queues = $this->laravel['bernard.queues'];


        $this->laravel['bernard.consumer']->consume(
            $queues->create($pullQueue),
            $failQueue ? $queues->create($failQueue) : null,
            [
                'max-retries' => $maxRetries,
                'max-runtime' => $maxRuntime
            ]
        );
    }
}