<?php

namespace Bernard\Laravel\Commands;

use Bernard\Message\DefaultMessage;
use Illuminate\Console\Command;

class BernardProduceCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'bernard:produce
                            {service : Name of the service (i.e. job), as registered in the bernard config.}
                            {data : JSON encoded data for the new job.}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Produce new job in the Bernard queue.';

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
        $service = $this->argument('service');
        if (!isset($this->laravel['config']['bernard.services'][$service])) {
            throw new \InvalidArgumentException("Service '$service' is not defined in bernard config.");
        }

        $data = $this->argument('data') ?: array();
        if ($data) {
            try {
                $data = json_decode($data, true);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("Failed to parse json data");
            }
        }

        $this->laravel['bernard.producer']->produce(new DefaultMessage($service, $data));
    }
}