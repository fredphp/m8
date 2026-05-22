<?php

namespace App\Console\Commands;

use App\Services\EthListenServices;
use function Composer\Autoload\includeFile;
use Illuminate\Console\Command;

class EthListen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eth-listen {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '监听以太坊到账';

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
     * @return mixed
     */
    public function handle()
    {
        $start = $this->argument('start');
        $end = $this->argument('end');
        if (!empty($start)) {
            (new EthListenServices())->sync($start, $end);
        } else {
            (new EthListenServices())->listening();
        }
    }
}
