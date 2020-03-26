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
    private $tag_groups;

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
		if($this->tag_groups){
			return $this->tag_groups;
		}
		$groups = TagGroup::whereIn("name->{$this->language}", ['Bottle Size', 'Case Size', 'Colour', 'Country', 'Region', 'Sub Region', 'Vintage', 'Wine Type', 'Burgundy Cru', 'Liv-Ex Order GUID'])->get();
		
		$this->tag_groups = [];
		foreach($groups as $g){
			$this->tag_groups[$g->name] = $g;
		}
		#Log::debug($this->tag_groups);
		return $this->tag_groups;
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
     * Get Liv-ex Order GUIDs and line info from the order items
     *
     * @param int $order_id
     * @return array
     */
    public function get_order_details($order_id){
		$order_guids = [];
		
		$dets = Tag::select("tags.name", "order_items.sku", "order_items.quantity")
		->join('tag_groups', 'tag_groups.id', '=', 'tags.tag_group_id')
		->join('product_tag', 'product_tag.tag_id', '=', 'tags.id')
		->join('products', 'products.id', '=', 'product_tag.product_id')
		->join('variants', 'variants.product_id', '=', 'products.id')
		->join('order_items', 'order_items.buyable_id', '=', 'variants.id')
		->where("tag_groups.name->{$this->language}", 'Liv-Ex Order GUID')
		->where('order_items.buyable_type', 'variant')
		->where('order_items.order_id', $order_id)->get();
		
		return $dets;
	}

    /**
     * Order Status API – check status of offers in basket (prior to checkout payment)
     *
     * @param \Aero\Cart\Models\Order $order
     * @param boolean $return_result
     * @return boolean | mixed if $return_result
     */
    public function order_status(\Aero\Cart\Models\Order $order, $return_result = false)
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
		$order_details = $this->get_order_details($order->id);
		#dd($order_details);
		$item_info = [];
		foreach($order_details as $det){
			$item_info[$det->name] = ['sku' => $det->sku, 'qty' => $det->quantity];
		}
		#dd($item_info);
		$order_guids = array_keys($item_info);
		#dd($order_guids);
		
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
				Log::debug($data);
				if($data['status'] == 'OK'){
					#dd($data);
					if($return_result){
						return $data['orderStatus']['status'];
					}
					
					foreach($data['orderStatus']['status'] as $order_status){
						
						#check if able to proceed with Aero order here...
						if($order_status['orderStatus'] ==  'S' or $order_status['orderStatus'] ==  'T'){
							#offer has been suspended or Traded - stop user from progressing through checkout
							$proceed_with_order = false;
						}
						
						if($order_status['quantity'] < $item_info[$order_status['orderGUID']]['quantity']){
							#offer has less qty available than user has in basket - stop user from progressing through checkout
							$proceed_with_order = false;
						}
					}
				}
				else{
					Log::warning(json_encode($data));
					if($data['error']['code'] == 'V056'){
						#GUID is not available or does not exist - removed from Liv-ex so prevent customer from proceeding
						$proceed_with_order = false;
					}
					
				}
			}
		}
		else{
			#Aero order has no Liv-ex items - no need for API request and continue with order checkout process
		}
		
		#dd($proceed_with_order);
		return $proceed_with_order;
    }

    /**
     * Orders API – send order to Liv-ex (after checkout payment)
     *
     * @param \Aero\Cart\Models\Order $order
     * @return void
     */
    public function add_order(\Aero\Cart\Models\Order $order)
    {
		$url = $this->base_url . 'exchange/v5/orders';
        $headers = [
			'CLIENT_KEY' => $this->get_client_key(),
			'CLIENT_SECRET' => $this->get_client_secret(),
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		
		#get the item status from Liv-ex - this is in nice format to then handle the params to post the order
		$dets = $this->order_status($order, true);
		#dd('order_status');
		#dd($dets);
		
		if(is_array($dets) and count($dets) > 0){
			
			Log::debug(__FUNCTION__);
			
			$orderItems = $order->items()->get();
			#dd($orderItems);
			
			foreach($dets as $iteminfo){
				foreach($orderItems as $item){
					$lwin18 = $iteminfo['lwin'] . $iteminfo['vintage'] . $iteminfo['bottleInCase'] . $iteminfo['bottleSize'];
					if(substr($item->sku, 0, -2) == 'LX'.$lwin18){
						
						#dd($item);
						$order_det[] = [
							'contractType' => $iteminfo['contractType'], #sib/sep/x
							#'orderType' => $iteminfo['orderType'],
							'orderType' => 'b', #bid
							'orderStatus' => $iteminfo['orderStatus'],
							'lwin' => $lwin18,
							'vintage' => $iteminfo['vintage'],
							'currency' => 'GBP',
							'price' => (int) $iteminfo['price'],
							'quantity' => $item->quantity,
							'merchantRef' => $order->id,
							'overrideFatFinger' => true, #Bypass system checks that prevent price keying errors.
						];
						
						#we post each order line separately so we can correctly store GUID against the SKU
						
						$params = ['orders' => $order_det];
						#dd($params);
						#dd(json_encode($params));

						$client = new Client();
						#$response = $client->post($url, ['headers' => $headers, 'json' => $params, 'debug' => true]);
						$response = $client->post($url, ['headers' => $headers, 'json' => $params]);

						$status_code = $response->getStatusCode(); // 200
						$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
						
						#Log::debug($status_code);
						
						if($body = $response->getBody()){
							$data = json_decode($response->getBody(), true);
							#dd($data);
							#Log::debug($data);
							if($data['status'] == 'OK'){
								foreach($data['orders']['order'] as $order_data){
									if($order_data['orderGUID']){
										$order->additional('livex_guid_'.$item->sku, $order_data['orderGUID']);
									}
									
									if($order_data['errors']){
										Log::warning(json_encode($data));
									}
								}
							}
							else{
								Log::warning(json_encode($data));
							}
						}
						else{
							Log::warning('unable to post order line '.$item->sku.' to Livex');
						}
					}
				}
			}
		}
		else{
			Log::warning('unable to post order '.$order->id.' to Livex');
		}
    }

    /**
     * Orders API – Delete order on Liv-ex if bid fails (after checkout payment)
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
			'priceType' => ['offer'], #ignore bids
			'dutyPaid' => false,
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
			#dd($data);
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
				
				if(isset($data['searchResponse'])){
					foreach($data['searchResponse'] as $item){
						#Log::debug($item);
						#Log::debug(json_encode($item));
						
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
							#dd($item);
						}
						
						foreach($markets as $market){
							if(!count($market['depth']['offers']['offer'])){
								#no active offers - skip this item
								#dd($item);
								$error++;
								Log::debug("ignore $sku no offers");
								continue;
							}
							$sku = $market['lwin']; #LWIN18
							$dutyPaid = $market['special']['dutyPaid']; #true/false
							$minimumQty = $market['special']['minimumQty'];
							$isCompetitive = $market['depth']['offers']['offer'][0]['isCompetitive'];
							
							$burgundy_cru = '';

							try{
								
								if($created_p >= 1){
									#die;
									#break;
								}
								
								if($market['depth']['offers']['offer'][0]['price'] < 250){
									$error++;
									Log::debug("ignore $sku due to price {$market['depth']['offers']['offer'][0]['price']}");
									continue;
								}
								
								if($minimumQty > 1){
									$error++;
									Log::debug("ignore $sku due to minimumQty {$minimumQty}");
									continue;
								}
								
								if($dutyPaid){
									$error++;
									Log::debug("ignore $sku due to dutyPaid {$dutyPaid}");
									continue;
								}
								
								$p = Product::where('model', 'LX'.$sku)->first();
								if($p != null){
									#already on system - just update the essentials
									Log::debug('update the variant LX'.$sku.' duty paid '.(int)$dutyPaid);
									#Log::debug($dutyPaid);
									#dd('update the variant LX'.$sku);
									
									if(!$p->allImages()->count()){
										#Handle image placeholder
										Log::debug($sku.' no image - add placeholder');
										$this->handlePlaceholderImage($p);
									}
									
									#check for orderGUID tag
									$order_guid = $market['depth']['offers']['offer'][0]['orderGUID'];
									$tag_group = $groups['Liv-Ex Order GUID'];
									$guid_tag = $p->tags()->where('tag_group_id', $tag_group->id)->first();
									if($guid_tag != null){
										#found tag - check if it's the same GUID
										
										#dd("found tag - check if it's the same GUID for $sku");
										#dd("{$order_guid} == {$guid_tag->name}");
										
										if($order_guid == $guid_tag->name){
											#it's the same - no action required
											#dd("it's the same - no action required");
										}
										else{
											#the current guid may be an older offer - let's update it
											#dd('current guid found but different - update guid to '.$order_guid.' for sku '.$sku);
											
											#delete the current one(s)
											$p->tags()->where('tag_group_id', $tag_group->id)->delete();
											
											#add the new guid
											$tag = $this->findOrCreateTag($order_guid, $tag_group);
											$p->tags()->syncWithoutDetaching($tag);
										}
									}
									else{
										#no order guid tag - let's add it (this might be an old item that has come back into stock)
										#dd('no guid found - add guid '.$order_guid.' for sku '.$sku);
										
										$tag = $this->findOrCreateTag($order_guid, $tag_group);
										$p->tags()->syncWithoutDetaching($tag);
									}
									
									#dd($p->id . ' - ' . $order_guid);
									
									
									$minimumQty = ($minimumQty) ? $minimumQty : 0;
									$p->variants()->update(['stock_level' => $market['depth']['offers']['offer'][0]['quantity'], 'minimum_quantity' => $minimumQty]);
									$updated++;
									
									$in_bond_item = $p->variants()->where('sku', $p->model.'IB')->first();
									if($in_bond_item != null){
										
										$price = $in_bond_item->prices()->where('quantity', 1)->first();
										if($price != null){
											$item_price = $market['depth']['offers']['offer'][0]['price'];
											$item_price_w_markup = $this->calculate_item_price($item_price);
											
											#dd("{$item_price} {$item_price_w_markup}");
											#dd("{$p->id} {$in_bond_item->sku} current {$price->value} new price {$item_price_w_markup}");
											#dd($price->value);
											#dd($item_price_w_markup);
											#Log::debug("{$p->id} {$in_bond_item->sku} current {$price->value} new price {$item_price_w_markup}");
											#update the price if different to current
											#dd([$item_price_w_markup, $price->value]);
											#Log::debug([$item_price_w_markup, $price->value]);
											
											#only update the price if we need to
											if($item_price_w_markup != $price->value){
												#dd([$item_price_w_markup, $price->value]);
												$price->update(['value' => $item_price_w_markup]);
												#Log::debug("{$p->id} {$in_bond_item->sku} variant price updated successfully new price {$item_price_w_markup}");
											}
										}
									}
								}
								else{
									#not currently on system - create it
									Log::debug('create LX'.$sku);
									#dd('create LX'.$sku);
									
									$case_size = (int) $market['packSize'];
									$bottle_size = $market['bottleSize']; #data in zero-padded millilitres e.g. 00750
									$bottle_size = self::format_bottle_size($bottle_size);
									Log::debug($bottle_size);
									
									
									$p = new Product;
									$p->model = 'LX'.$sku;
									$p->name = $name;
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
										
										
										$order_guid = $market['depth']['offers']['offer'][0]['orderGUID'];
										
										$tag_group = $groups['Liv-Ex Order GUID'];
										$tag = $this->findOrCreateTag($order_guid, $tag_group);
										$p->tags()->syncWithoutDetaching($tag);
										
										#create the in-bond variant
										$variant = new Variant;
										$variant->product_id = $p->id;
										$variant->stock_level = $market['depth']['offers']['offer'][0]['quantity'];
										$variant->minimum_quantity = ($minimumQty) ? $minimumQty : 0;
										$variant->sku = $p->model.'IB';
										$variant->product_tax_group_id = 2; #non-taxable
										if($dutyPaid){
											$variant->sku = $p->model.'DP';
											$variant->product_tax_group_id = 1; #taxable
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
											
											$item_price = $market['depth']['offers']['offer'][0]['price'];
											$item_price_w_markup = $this->calculate_item_price($item_price);
											Log::debug($item_price);
											Log::debug($item_price_w_markup);
											$price->value = $item_price_w_markup;
											
											if($price->save()){
												Log::debug('variant price created successfully');
											}
											else{
												Log::debug('variant price failed to create');
											}
										}
										else{
											$create_v_failed++;
											
											Log::debug('variant '.$variant->sku.' failed to create');
										}
										
										#Handle image
										$this->handlePlaceholderImage($p);
									}
									else{
										$create_p_failed++;
									}
								}
								
								#dd($p);
								#dd($p->id);
							}
							catch(ErrorException  $e){
								Log::warning($e);
							}
							catch(Exception $e){
								Log::warning($e);
							}
						} #end markets loop
					} #end search response loop
					
					
					Log::debug("Search API complete");
					Log::debug("created products $created_p/$total | created variants $created_v/$total | failed products $create_p_failed/$total | failed variants $create_v_failed/$total | updated $updated/$total | update failed $update_failed/$total | ignored $error/$total");
					
				} #end check for search response
			}
			else{
				Log::warning(json_encode($data));
			}
		}
    }

    /**
     * Calculate total value of items in basket of Livex items to prevent CC option in checkout
     *
     * @param \Aero\Cart\Cart $group
     * @return boolean
     */
    public function basket_items_limit_reached(\Aero\Cart\Cart $cart)
    {
		#dd($cart->items());
		$items = $cart->items();
		$livex_total = 0;
		if(!$items->isEmpty()){
			foreach($items as $item){
				if(substr($item->sku, 0, 2) == 'LX'){
					$livex_total += $item->subtotal();
				}
			}
		}
		
		if($livex_total > (setting('Livex.max_subtotal_in_basket') * 100)){
			return true;
		}
		return false;
    }

    /**
     * Calculate item price with added markup
     *
     * @param float $item_price
     * @return int
     */
    public function calculate_item_price($item_price)
    {
		$item_price_w_markup = (float) $item_price;
		if($item_price >= 500){
			$item_price_w_markup = $item_price * (1 + (setting('Livex.margin_markup') / 100));
		}
		elseif($item_price >= 250 and $item_price < 500){
			$item_price_w_markup = $item_price * (1 + (setting('Livex.margin_markup') / 100)) + 25;
		}
		return $item_price_w_markup * 100; #Aero stores price as int
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
     * @return void
     */
    public function handlePlaceholderImage(\Aero\Catalog\Models\Product $product)
    {
		$image_src = null;
		
		#Log::debug(__FUNCTION__);
		
		if(!$this->library_files){
			$files = File::files(storage_path('app/image_library/library/'));
			foreach($files as $file){
				$this->library_files[] = pathinfo($file)['basename'];
			}
		}
		#dd($this->library_files);
		
		$groups = $this->get_tag_groups();
		#dd($groups);
		
		$wine_type = '';
		$colour = '';
		
		$tag_group = $groups['Wine Type'];
		$tag = $product->tags()->where('tag_group_id', $tag_group->id)->first();
		if($tag != null){
			$wine_type = $tag->name;
		}
		
		$tag_group = $groups['Colour'];
		$tag = $product->tags()->where('tag_group_id', $tag_group->id)->first();
		if($tag != null){
			$colour = $tag->name;
		}
		
		
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
			Log::debug($product->model.' use library image - '.$image_src);
		}
		else{
			#deduce image from the colour/type using the plain default images 
			
			if($wine_type == 'Sparkling'){
				if($colour == 'Rose'){
					$image_name = 'sparklingrose.png';
				}
				else{
					$image_name = 'sparkling.png';
				}
			}
			elseif($wine_type == 'Fortified'){
				$image_name = 'fortified.png';
			}
			elseif($colour == 'Red'){
				$image_name = 'red.png';
			}
			elseif($colour == 'Rose'){
				$image_name = 'rose.png';
			}
			elseif($colour == 'White'){
				$image_name = 'white.png';
			}
			
			if($image_name){
				$image_src = storage_path('app/image_library/defaults/'.$image_name);
				Log::debug($product->model.' use default image - '.$image_src);
			}
			else{
				Log::debug($product->model.' unable to create from default image - '.$wine_type.' | '.$colour);
			}
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
