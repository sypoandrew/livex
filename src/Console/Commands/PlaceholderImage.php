<?php

namespace Sypo\Livex\Console\Commands;

use Illuminate\Console\Command;
use Sypo\Livex\Models\Livex;

class PlaceholderImage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sypo:livex:image';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create placeholder images for products';

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
		$l = new Livex;
		$groups = $l->get_tag_groups();
		
		$products = Product::join('product_images', 'product_images.product_id', '=', 'products.id')->where('product_images.product_id', 'is', 'null');
		Log::debug($products->toSql());
		/* $products = $products->get();
		
		
		foreach($products as $product){
			#Handle image placeholder
			Log::debug($sku.' - add placeholder');
			$this->handlePlaceholderImage($product, $wine_type, $colour);
		} */
    }
}
