<?php

namespace Sypo\Livex\Console\Commands;

use Illuminate\Console\Command;
use Sypo\Livex\Models\Refund;
use Symfony\Component\Console\Helper\ProgressBar;

class ProcessRefund extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sypo:livex:refund';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process refund for Liv-ex orders that failed to trade';

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
        $r = new Refund;
		$r->check_for_refund();
		
		if($r->getCount()){
			$this->info('processed '.$r->getCount().' orders');
		}
		else{
			$this->info('no orders to process');
		}
    }
}
