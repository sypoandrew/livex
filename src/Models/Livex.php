<?php

namespace Sypo\Livex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Aero\Catalog\Models\Price;
use Aero\Catalog\Models\Product;
use Aero\Catalog\Models\Variant;
use Aero\Catalog\Models\Tag;
use Aero\Catalog\Models\TagGroup;
use Aero\Catalog\Models\Attribute;
use Aero\Common\Models\Currency;
use Aero\Common\Models\Image;
use Aero\Cart\Models\Order;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;

class Livex extends Model
{
    /**
     * @var string
     */
    protected $language;
    protected $environment;
    protected $base_url;
    private $library_files;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->language = config('app.locale');
        $this->environment = env('LIVEX_ENV');
        $this->base_url = 'https://sandbox-api.liv-ex.com/';
        if($this->environment == 'live'){
			$this->base_url = 'https://api.liv-ex.com/';
		}
        parent::__construct();
    }

    /**
     * Get the client API key
     *
     * @return string
     */
    private function get_client_key()
    {
        if($this->environment == 'live'){
			return env('LIVEX_CLIENT_KEY');
		}
        return env('LIVEX_CLIENT_KEY_SANDBOX');
    }

    /**
     * Get the client API secret
     *
     * @return string
     */
    private function get_client_secret()
    {
        if($this->environment == 'live'){
			return env('LIVEX_CLIENT_SECRET');
		}
        return env('LIVEX_CLIENT_SECRET_SANDBOX');
    }

    /**
     * Get all required tag groups for importing tag data
     *
     * @return Aero\Catalog\Models\TagGroup
     */
    protected function get_tag_groups()
    {
		$groups = TagGroup::whereIn("name->{$this->language}", ['Bottle Size', 'Case Size', 'Colour', 'Country', 'Region', 'Sub Region', 'Vintage', 'Wine Type', 'Burgundy Cru', 'Liv-Ex Order GUID'])->get();
		
		$arr = [];
		foreach($groups as $g){
			$arr[$g->name] = $g;
		}
		#Log::debug($arr);
		return $arr;
    }

    /**
     * Heartbeat API – Check that Liv-ex is up and available
     *
     * @return boolean
     */
    public function heartbeat()
    {
        $url = $this->base_url . 'exchange/heartbeat';
        $headers = [
			'CLIENT_KEY' => $this->get_client_key(),
			'CLIENT_SECRET' => $this->get_client_secret(),
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		#Log::debug($headers);
		
		try {
			#$client = new Client();
			#$client->setDefaultOption('headers', $headers);
			#$response = $client->request('GET', $url);
			
			$client = new Client();
			$response = $client->get($url, ['headers' => $headers]);
			
			$status_code = $response->getStatusCode(); // 200
			$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
			
			Log::debug(__FUNCTION__);
			#Log::debug($status_code);
			
			$data = json_decode($response->getBody(), true);
			Log::debug($data);
			if($data['status'] == 'OK'){
				return true;
			}
			else{
				Log::warning(json_encode($data));
			}
		}
		catch(RequestException $e) {
			Log::warning($e);
		}
		
		return false;
    }

    /**
     * Calculate total value of items in basket of Livex items to prevent CC option in checkout
     *
     * @return boolean
     */
    public function basket_limit_livex_items_reached()
    {
		
    }
	
	/**
     * Get Liv-ex Order GUIDs from the order items
     *
     * @param int $order_id
     * @return array
     */
    public function get_order_guids($order_id){
		$order_guids = [];
		
		$tags = Tag::select("tags.name")
		->join('tag_groups', 'tag_groups.id', '=', 'tags.tag_group_id')
		->join('tag_variant', 'tag_variant.tag_id', '=', 'tags.id')
		->join('variants', 'variants.id', '=', 'tag_variant.variant_id')
		->join('order_items', 'order_items.buyable_id', '=', 'variants.id')
		->where("tag_groups.name->{$this->language}", 'Liv-Ex Order GUID')
		->where('order_items.buyable_type', 'variant')
		->where('order_items.order_id', $order_id)->get();
		
		foreach($tags as $tag){
			$order_guids[] = $tag->name;
		}
	}

    /**
     * Order Status API – check status of offers in basket (prior to checkout payment)
     *
     * @param \Aero\Cart\Models\Order $order
     * @return boolean
     */
    public function order_status(\Aero\Cart\Models\Order $order)
    {
		$proceed_with_order = true;
		
        $url = $this->base_url . 'exchange/v1/orderStatus';
        $headers = [
			'CLIENT_KEY' => $this->get_client_key(),
			'CLIENT_SECRET' => $this->get_client_secret(),
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		
		#get the Liv-ex order GUIDs from the Aero order items
		$order_guids = $this->get_order_guids($order->id);
		#dd($order_guids);
		#testing
		$order_guids = [];
		$order_guids[] = '5b3932f5-06bb-4f1e-b73f-589a7b3a2d51';
		
		if($order_guids){
			$params = [
				'orderGUID' => $order_guids,
			];


			$client = new Client();
			#$response = $client->post($url, ['headers' => $headers, 'json' => $params, 'debug' => true]);
			$response = $client->post($url, ['headers' => $headers, 'json' => $params]);

			$status_code = $response->getStatusCode(); // 200
			$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
			
			Log::debug(__FUNCTION__);
			#Log::debug($status_code);
			
			
			if($body = $response->getBody()){
				$data = json_decode($response->getBody(), true);
				#dd($data);
				if($data['status'] == 'OK'){
					dd($data);
					foreach($data['orderStatus']['status'] as $order_status){
						
						################################
						# TODO - may need further testing for correct checking of orders
						################################
						
						#check if able to proceed with Aero order here...
						if($order_status['orderStatus'] ==  'S'){
							#offer has been suspended - stop user from progressing through checkout
							$proceed_with_order = false;
						}
					}
				}
				else{
					Log::warning(json_encode($data));
				}
			}
		}
		else{
			#Aero order has no Liv-ex items - no need for API request and continue with order checkout process
		}
		
		dd($proceed_with_order);
		return $proceed_with_order;
    }

    /**
     * Orders API – check status of offers in basket (prior to checkout payment)
     *
     * @param \Aero\Cart\Models\Order $order
     * @return void
     */
    public function add_order(\Aero\Cart\Models\Order $order)
    {
		$url = $this->base_url . 'exchange/v4/orders';
        $headers = [
			'CLIENT_KEY' => $this->get_client_key(),
			'CLIENT_SECRET' => $this->get_client_secret(),
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		
		$orders = [];
		
		################################
		# TODO - correctly handle Livex orders
		################################
		$orders[] = [
			'contractType' => '', #sib/sep/x
			'orderType' => 'O',
			'orderStatus' => 'L',
		];
		
		$params = $orders;


		$client = new Client();
		#$response = $client->post($url, ['headers' => $headers, 'json' => $params, 'debug' => true]);
		$response = $client->post($url, ['headers' => $headers, 'json' => $params]);

		$status_code = $response->getStatusCode(); // 200
		$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
		
		Log::debug(__FUNCTION__);
		#Log::debug($status_code);
		
		if($body = $response->getBody()){
			$data = json_decode($response->getBody(), true);
			#dd($data);
			if($data['status'] == 'OK'){
				foreach($data['orderStatus']['status'] as $order_status){
					
					#check if able to proceed with Aero order here...
					if($order_status['orderStatus'] ==  'S'){
						#offer has been suspended - stop user from progressing through checkout
						$proceed_with_order = false;
					}
				}
			}
			else{
				Log::warning(json_encode($data));
			}
		}
    }

    /**
     * Orders API – Check for failed bids and delete (after checkout payment)
     *
     * @param \Aero\Cart\Models\Order $order
     * @return boolean
     */
    public function cancel_order(\Aero\Cart\Models\Order $order)
    {
		if($order->additional('livex_guid')){
			$url = $this->base_url . 'exchange/v4/orders';
			$headers = [
				'CLIENT_KEY' => $this->get_client_key(),
				'CLIENT_SECRET' => $this->get_client_secret(),
				'ACCEPT' => 'application/json',
				'CONTENT-TYPE' => 'application/json',
			];
			
			$params = [
				'orderGUID' => $order->additional('livex_guid'),
			];


			$client = new Client();
			#$response = $client->request('DELETE', $url, ['headers' => $headers, 'json' => $params, 'debug' => true]);
			$response = $client->request('DELETE', $url, ['headers' => $headers, 'json' => $params]);

			$status_code = $response->getStatusCode(); // 200
			$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
			
			Log::debug(__FUNCTION__);
			#Log::debug($status_code);
			
			if($body = $response->getBody()){
				$data = json_decode($response->getBody(), true);
				#dd($data);
				if($data['status'] == 'OK'){
					return true;
				}
				else{
					Log::warning(json_encode($data));
				}
			}
		}
		
		return false;
    }

    /**
     * Search Market API – Receive live Bids and Offers based on defined filters
     *
     * @return void
     */
    public function search_market()
    {
        $url = $this->base_url . 'search/v1/searchMarket';
        $headers = [
			'CLIENT_KEY' => $this->get_client_key(),
			'CLIENT_SECRET' => $this->get_client_secret(),
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		
		$params = [
			#'lwin' => [18], #LWIN11/LWIN16/LWIN18
			'currency' => 'gbp',
			#'minPrice' => setting('Livex.price_threshold'),
			#'priceType' => 'offer', #ignore bids
			'dutyPaid' => true,
			#'condition' => '',
			#'isCompetitive' => true,
		];


		$client = new Client();
		#$response = $client->post($url, ['headers' => $headers, 'json' => $params, 'debug' => true]);
		$response = $client->post($url, ['headers' => $headers, 'json' => $params]);

		$status_code = $response->getStatusCode(); // 200
		$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
		
		Log::debug(__FUNCTION__);
		#Log::debug($status_code);
		
		$groups = $this->get_tag_groups();
		#Log::debug($groups);
		#dd($groups);
		
		$categories = [3, 5]; #Buy Wine | Liv-Ex wines
		
		$attr = [];
		$attributes = Attribute::select('id', 'name')->get();
		foreach($attributes as $a){
			$attr[$a->name] = $a->id;
		}
		#dd($attr);
		
		$currency = Currency::where('code', 'GBP')->first();
		
		if($body = $response->getBody()){
			$data = json_decode($response->getBody(), true);
			
			Log::debug($data);
			#Log::debug($data['pageInfo']);
			if($data['status'] == 'OK'){
				$total = $data['pageInfo']['totalResults'];
				$i = 0;
				$created_p = 0;
				$created_v = 0;
				$create_p_failed = 0;
				$create_v_failed = 0;
				$updated = 0;
				$update_failed = 0;
				$error = 0;
				$vinquinnStockItem = 0;
				
				if(isset($data['searchResponse'])){
					foreach($data['searchResponse'] as $item){
						Log::debug($item);
						
						$i++;
						
						$lwin = $item['lwin'];
						$name = $item['lwinName'];
						$country = $item['lwinCountry'];
						$region = $item['lwinRegion'];
						$subregion = $item['lwinSubRegion'];
						$colour = $item['lwinColour'];
						$vintage = $item['vintage'];
						
						#dd($item);

						$markets = $item['market'];
						if(count($markets) > 1){
							#handle multi market differently??
						}
						
						if(!$markets){
							dd($item);
						}
						
						foreach($markets as $market){
							if(!count($market['depth']['offers']['offer'])){
								#no active offers - skip this item
								#dd($item);
								continue;
							}
							$sku = $market['lwin']; #LWIN18
							$dutyPaid = $market['special']['dutyPaid']; #true/false
							$minimumQty = $market['special']['minimumQty'];
							
							$burgundy_cru = '';

							try{
								
								if($created_p >= 1){
									die;
								}
								
								
								
								$p = Product::where('model', 'LX'.$sku)->first();
								if($p != null){
									#already on system - just update the essentials
									Log::debug('update the variant LX'.$sku);
									#dd('update the variant LX'.$sku);
									
									if($dutyPaid){
										$variant = Variant::where('product_id', $p->id)->where('sku', 'like', '%DP')->first();
									}
									else{
										$variant = Variant::where('product_id', $p->id)->where('sku', 'like', '%IB')->first();
									}
									
									$variant->stock_level = $market['depth']['offers']['offer'][0]['quantity'];
									$variant->minimum_quantity = ($minimumQty) ? $minimumQty : 0;
									if($variant->save()){
										$updated++;
										Log::debug('update variant LX'.$sku.' success');
										#dd('update variant LX'.$sku.' success');
									}
									else{
										$update_failed++;
										Log::debug('update variant LX'.$sku.' failed');
										#dd('update variant LX'.$sku.' failed');
									}
									#dd($p->id);
								}
								else{
									#not currently on system - create it
									Log::debug('create LX'.$sku);
									#dd('create LX'.$sku);
									
									$case_size = $market['packSize'];
									$bottle_size = $market['bottleSize']; #data in zero-padded millilitres e.g. 00750
									$bottle_size = self::format_bottle_size($bottle_size);
									Log::debug($bottle_size);
									
									
									$p = new Product;
									$p->model = 'LX'.$sku;
									$p->name = $name;
									#$p->summary = ['en' => $name];
									#$p->description = ['en' => $name];
									$p->summary = $name;
									$p->description = $name;
									$p->active = false; #initially hide - to be vetted prior to listing on website
									$p->type = 'variant';
									
									
									if($p->save()){
										$created_p++;
										
										#add into categories
										foreach($categories as $category_id){
											$p->categories()->syncWithoutDetaching([$category_id => ['sort' => $p->categories()->count()]]);
										}
										
										#do some assumptions for wine type...
										if($country == 'Portugal'){
											$wine_type = 'Fortified';
										}
										elseif($region == 'Champagne'){
											$wine_type = 'Sparkling';
										}
										else{
											$wine_type = 'Still';
										}
										
										#Bottle Size tag
										$tag_group = $groups['Bottle Size'];
										$tag = $this->findOrCreateTag($bottle_size, $tag_group);
										$p->tags()->syncWithoutDetaching($tag);
										
										#Case Size tag
										$tag_group = $groups['Case Size'];
										$tag = $this->findOrCreateTag($case_size, $tag_group);
										$p->tags()->syncWithoutDetaching($tag);
										
										#Wine Type tag
										$tag_group = $groups['Wine Type'];
										$tag = $this->findOrCreateTag($wine_type, $tag_group);
										$p->tags()->syncWithoutDetaching($tag);
										
										#Country tag
										$tag_group = $groups['Country'];
										$tag = $this->findOrCreateTag($country, $tag_group);
										$p->tags()->syncWithoutDetaching($tag);
										
										#Region tag
										$tag_group = $groups['Region'];
										$tag = $this->findOrCreateTag($region, $tag_group);
										$p->tags()->syncWithoutDetaching($tag);
										
										#Sub Region tag
										if($subregion){
											$tag_group = $groups['Sub Region'];
											$tag = $this->findOrCreateTag($subregion, $tag_group);
											$p->tags()->syncWithoutDetaching($tag);
										}
										
										#Colour tag
										$tag_group = $groups['Colour'];
										$tag = $this->findOrCreateTag($colour, $tag_group);
										$p->tags()->syncWithoutDetaching($tag);
										
										#Vintage tag
										$tag_group = $groups['Vintage'];
										$tag = $this->findOrCreateTag($vintage, $tag_group);
										$p->tags()->syncWithoutDetaching($tag);
										
										#Burgundy Cru tag
										if($burgundy_cru){
											$tag_group = $groups['Burgundy Cru'];
											$tag = $this->findOrCreateTag($burgundy_cru, $tag_group);
											$p->tags()->syncWithoutDetaching($tag);
										}
										
										#create the in-bond variant
										$variant = new Variant;
										$variant->product_id = $p->id;
										$variant->stock_level = $market['depth']['offers']['offer'][0]['quantity'];
										$variant->minimum_quantity = ($minimumQty) ? $minimumQty : 0;
										$variant->sku = $p->model.'IB';
										if($dutyPaid){
											$variant->sku = $p->model.'DP';
										}
										if($variant->save()){
											$created_v++;
											
											Log::debug('variant '.$variant->sku.' created successfully');
											
											#add the attribute for the variant Bond/Duty Paid
											if($dutyPaid){
												$variant->attributes()->syncWithoutDetaching([$attr['Duty Paid'] => ['sort' => $variant->attributes()->count()]]);
											}
											else{
												$variant->attributes()->syncWithoutDetaching([$attr['Bond'] => ['sort' => $variant->attributes()->count()]]);
											}
											
											if(!$variant->attributes()->count()){
												Log::debug('variant attribute failed to create');
											}
											
											#add the variant price
											$price = new Price([
												'variant_id' => $variant->id,
												'product_tax_group_id' => $variant->product_tax_group_id,
												'product_id' => $p->id,
												'quantity' => 1,
												'currency_code' => $currency->code,
											]);
											
											$price->value = $market['depth']['offers']['offer'][0]['price'] * 100;
											
											if($price->save()){
												Log::debug('variant price created successfully');
											}
											else{
												Log::debug('variant price failed to create');
											}
											
											$order_guid = $market['depth']['offers']['offer'][0]['orderGUID'];
											
											$tag_group = $groups['Liv-Ex Order GUID'];
											$tag = $this->findOrCreateTag($order_guid, $tag_group);
											$variant->tags()->syncWithoutDetaching($tag);
										}
										else{
											$create_v_failed++;
											
											Log::debug('variant '.$variant->sku.' failed to create');
										}
										
										#Handle image
										$this->handlePlaceholderImage($p, $wine_type, $colour);
									}
									else{
										$create_p_failed++;
									}
								}
								
								#dd($p);
								#dd($p->id);
								#die;
							}
							catch(ErrorException  $e){
								Log::warning($e);
							}
							catch(Exception $e){
								Log::warning($e);
							}
						} #end markets loop
					} #end search response loop
				} #end check for search response
			}
			else{
				Log::warning(json_encode($data));
			}
		}
    }

    /**
     * @param $name
     * @param \Aero\Catalog\Models\TagGroup $group
     * @return \Aero\Catalog\Models\Tag
     */
    protected function findOrCreateTag($name, TagGroup $group)
    {
        $tag = $group->tags()->where("name->{$this->language}", $name)->first();

        if (! $tag) {
            $tag = new Tag();
            $tag->setTranslation('name', $this->language, $name);

            $group->tags()->save($tag);
        }

        return $tag;
    }

    /**
     * @param \Aero\Catalog\Models\Product $product
     * @param string $wine_type
     * @param string $colour
     * @return void
     */
    protected function handlePlaceholderImage(\Aero\Catalog\Models\Product $product, $wine_type, $colour)
    {
		$image_src = null;
		
		Log::debug(__FUNCTION__);
		
		if(!$this->library_files){
			$files = File::files(storage_path('app/image_library/library/'));
			foreach($files as $file){
				$this->library_files[] = pathinfo($file)['basename'];
			}
		}
		#dd($this->library_files);
		
		$image_name = '';
		
		#check if we have one in the library
		# - first check LWIN7 with space
		$lwin7 = substr(str_replace('LX', '', $product->model), 0, 7);
		$lwin7_found = false;
		foreach($this->library_files as $filename){
			if(substr($filename, 0, 8) == $lwin7 . ' '){
				$lwin7_found = true;
				$image_name = $filename;
				break;
			}
		}
		
		# - second check LWIN6 with space
		if(!$lwin7_found){
			$lwin6 = substr(str_replace('LX', '', $product->model), 0, 6);
			$lwin6_found = false;
			
			foreach($this->library_files as $filename){
				if(substr($filename, 0, 7) == $lwin6 . ' '){
					$lwin6_found = true;
					$image_name = $filename;
					break;
				}
			}
		}
		
		if($image_name){
			$image_src = storage_path('app/image_library/library/'.$image_name);
			Log::debug('use library image - '.$image_src);
		}
		else{
			#deduce image from the colour/type using the plain default images 
			
			if($wine_type == 'sparkling'){
				if($colour == 'rose'){
					$image_name = 'sparklingrose.png';
				}
				else{
					$image_name = 'sparkling.png';
				}
			}
			elseif($wine_type == 'fortified'){
				$image_name = 'fortified.png';
			}
			elseif($colour == 'red'){
				$image_name = 'red.png';
			}
			elseif($colour == 'rose'){
				$image_name = 'rose.png';
			}
			elseif($colour == 'white'){
				$image_name = 'white.png';
			}
			
			if($image_name){
				$image_src = storage_path('app/image_library/defaults/'.$image_name);
			}
			Log::debug('use default image - '.$image_src);
		}
		
		if($image_src !== null){
			$this->createOrUpdateImage($product, $image_src);
		}
	}

    /**
     * @param \Aero\Catalog\Models\Product $product
     * @param string $src
     * @return void
     */
    protected function createOrUpdateImage(\Aero\Catalog\Models\Product $product, $src)
    {
        $image = null;
        $existing = null;
        $update = null;

        if (isset($src)) {
            $temp = tempnam(sys_get_temp_dir(), 'aero-product-image');

            $url = $src;

            try {
                $context = stream_context_create([
                    'http' => [
                        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.120 Safari/537.36',
                    ],
                ]);

                $image = file_get_contents($url, false, $context);
            } catch (\Exception $e) {
                Log::warning("Error downloading image {$url}: {$e->getMessage()}");
                $image = null;
            }

            if ($image) {
                file_put_contents($temp, $image);

                $file = new UploadedFile($temp, basename($url));

                $type = $file->getMimeType();
                $hash = md5(file_get_contents($file->getRealPath()));

                $image = Image::where('hash', $hash)->first();

                if (! $image) {
                    try {
                        [$width, $height] = getimagesize($file->getRealPath());

                        $name = $file->storePublicly('images/products', 'public');

                        $image = Image::create([
                            'file' => $name,
                            'type' => $type,
                            'width' => $width,
                            'height' => $height,
                            'hash' => $hash,
                            'source' => $url,
                        ]);
                    } catch (\Exception $e) {
                        Log::warning("Error processing image {$url}: {$e->getMessage()}");
                        $image = null;
                    }
                }
            }

            unlink($temp);
        }

        $position = null;
        $attribute = null;

        $default = true;

        if ($image) {
            $existing = $product->allImages()->where('image_id', $image->id)->first();
        }

        if (! $existing && $image) {
            $position = $product->allImages()->count();

            /** @var $update \Aero\Catalog\Models\ProductImage */
            $update = $product->allImages()->create([
                'image_id' => $image->id,
                'default' => $default,
                'sort' => $position,
            ]);

            if ($attribute) {
                $update->attributes()->syncWithoutDetaching([$attribute->id => ['sort' => $position]]);
            }
        } elseif ($existing) {
            $attributes = [
                'default' => $default,
            ];

            $existing->update($attributes);

            $update = $existing;
        }

        if ($update) {
            $update->save();
        }
    }

    /**
     * @param string $bottle_size
     * 
     * @return string
     */
	public static function format_bottle_size($bottle_size){
		$size = (int) $bottle_size;
		if($size < 1000){
			return $size.'ml';
		}
		return number_format(($size / 1000), 1).'l';
	}
}
