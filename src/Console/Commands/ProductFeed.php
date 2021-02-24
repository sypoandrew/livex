<?php

namespace Sypo\Livex\Console\Commands;

use Illuminate\Console\Command;
use Sypo\Livex\Models\LivexFeed;

class ProductFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sypo:livex:feed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Liv-ex product feed';

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
        $l = new LivexFeed;
		$res = $l->call();
		if($res['success']){
			$this->info('Liv-ex product feed generated successfully');
			$this->info($res['processed'] . ' items processed');
		}
		else{
			$this->info('Liv-ex product feed failed. Please review errors below:');
			foreach($res['error'] as $err){
				$this->info($err);
			}
		}
    }
}
