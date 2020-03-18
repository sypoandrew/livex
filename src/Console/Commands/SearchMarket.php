<?php

namespace Sypo\Livex\Console\Commands;

use Illuminate\Console\Command;

class SearchMarket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'livex:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Liv-ex API to search market for offers';

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
        $l = new \Sypo\Livex\Models\Livex;
		$l->search_market();
    }
}
