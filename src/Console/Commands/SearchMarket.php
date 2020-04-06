<?php

namespace Sypo\Livex\Console\Commands;

use Illuminate\Console\Command;
use Sypo\Livex\Models\SearchMarketAPI;

class SearchMarket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sypo:livex:import';

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
        $l = new SearchMarketAPI;
		$l->call();
		
		$this->info('Process complete');
		$this->info("processed {$l->result['i']}/{$l->result['count']}");
		$this->info("created products {$l->result['created_p']}/{$l->result['count']}");
		$this->info("created variants {$l->result['created_v']}/{$l->result['count']}");
		$this->info("failed products {$l->result['create_p_failed']}/{$l->result['count']}");
		$this->info("failed variants {$l->result['create_v_failed']}/{$l->result['count']}");
		$this->info("updated {$l->result['updated']}/{$l->result['count']}");
		$this->info("update failed {$l->result['update_failed']}/{$l->result['count']}");
		$this->info("ignored {$l->result['error']}/{$l->result['count']}");
    }
}
