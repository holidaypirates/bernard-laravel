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
        $maxRuntime = $this->option('max-runtime');
        $queues = $this->laravel['bernard.queues'];


        $this->laravel['bernard.consumer']->consume(
            $queues->create($pullQueue),
            [
                'max-runtime' => $maxRuntime
            ]
        );
    }
}