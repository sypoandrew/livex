<?php

namespace Sypo\Livex\Console\Commands;

use Illuminate\Console\Command;
use Sypo\Livex\Models\Image;
use Aero\Catalog\Models\Product;
use Illuminate\Support\Facades\Log;
use Mail;
use Sypo\Livex\Mail\ImageReport;
use Symfony\Component\Console\Helper\ProgressBar;

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
		$l = new Image;
		$products = $this->get_products_without_images();
		
        $progressBar = new ProgressBar($this->output, $products->count());
		
		foreach($products as $product){
			#Handle image placeholder
			#Log::debug($product->model.' - add placeholder image');
			$l->handlePlaceholderImage($product);
			$progressBar->advance();
		}
		
		#send report to Simon on items with missing products
		$products = $this->get_products_without_images(true);
		$email = new ImageReport($products);
		Mail::send($email);
		
		$progressBar->finish();
    }
	
	protected function get_products_without_images($cutdown = false){
		if($cutdown){
			$products = Product::select('products.model', 'products.name')->leftJoin('product_images', 'product_images.product_id', '=', 'products.id')->whereNull('product_images.product_id');
		} else{
			$products = Product::select('products.*')->leftJoin('product_images', 'product_images.product_id', '=', 'products.id')->whereNull('product_images.product_id');
		}
		#Log::debug($products->toSql());
		$products = $products->get();
		
		return $products;
	}
}
