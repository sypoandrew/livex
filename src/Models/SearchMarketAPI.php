<?php

namespace Sypo\Livex\Models;

use Illuminate\Support\Facades\Log;
use Aero\Catalog\Events\ProductCreated;
use Aero\Catalog\Events\ProductUpdated;
use Aero\Catalog\Models\Attribute;
use Aero\Catalog\Models\Price;
use Aero\Catalog\Models\Product;
use Aero\Catalog\Models\Tag;
use Aero\Catalog\Models\Variant;
use Aero\Common\Models\Currency;
use Sypo\Livex\Models\LivexAPI;
use Sypo\Livex\Models\Helper;
use Sypo\Livex\Models\Image as PlaceholderImage;

class SearchMarketAPI extends LivexAPI
{
	protected $currency;
    protected $tag_groups;
    protected $attributes;
    protected $count;
    protected $i;
    /**
     * Storage of products that have been processed.
     *
     * @var array
     */
    protected $products = ['created' => [], 'updated' => []];


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
		$this->currency = Currency::where('code', 'GBP')->first();
		$this->tag_groups = Helper::get_tag_groups();
		$this->attributes = [];
		$attributes = Attribute::select('id', 'name')->get();
		foreach($attributes as $a){
			$this->attributes[$a->name] = $a->id;
		}
		
        parent::__construct();
	}
	
	
    /**
     * Search Market API – Receive live Bids and Offers based on defined filters
     *
     * @return void
     */
    public function call()
    {
        $url = $this->base_url . 'search/v1/searchMarket';
		
		$params = [
			#'lwin' => [18], #LWIN11/LWIN16/LWIN18
			'currency' => 'gbp',
			#'minPrice' => setting('Livex.price_threshold'),
			'priceType' => ['offer'], #ignore bids
			'dutyPaid' => false,
			#'condition' => '',
			#'isCompetitive' => true,
		];


		#$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params, 'debug' => true]);
		$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params]);
		$this->set_responsedata();
		
		#Log::debug(__FUNCTION__);
		#Log::debug($status_code);
		
		$categories = [3, 5]; #Buy Wine | Liv-Ex wines
		
		#Log::debug($this->responsedata);
		#dd($this->responsedata);
		#Log::debug($this->responsedata['pageInfo']);
		if($this->responsedata['status'] == 'OK'){
			$this->count = $this->responsedata['pageInfo']['totalResults'];
			$this->i = 0;
			$created_p = 0;
			$created_v = 0;
			$create_p_failed = 0;
			$create_v_failed = 0;
			$updated = 0;
			$update_failed = 0;
			$error = 0;
			
			$img = new PlaceholderImage;
			
			if(isset($this->responsedata['searchResponse'])){
				foreach($this->responsedata['searchResponse'] as $item){
					#Log::debug($item);
					#Log::debug(json_encode($item));
					
					$this->i++;
					
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
									$img->handlePlaceholderImage($p);
								}
								
								#check for orderGUID tag
								$order_guid = $market['depth']['offers']['offer'][0]['orderGUID'];
								$tag_group = $this->tag_groups['Liv-Ex Order GUID'];
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
									$tag_group = $this->tag_groups['Bottle Size'];
									$tag = $this->findOrCreateTag($bottle_size, $tag_group);
									$p->tags()->syncWithoutDetaching($tag);
									
									#Case Size tag
									$tag_group = $this->tag_groups['Case Size'];
									$tag = $this->findOrCreateTag($case_size, $tag_group);
									$p->tags()->syncWithoutDetaching($tag);
									
									#Wine Type tag
									$tag_group = $this->tag_groups['Wine Type'];
									$tag = $this->findOrCreateTag($wine_type, $tag_group);
									$p->tags()->syncWithoutDetaching($tag);
									
									#Country tag
									$tag_group = $this->tag_groups['Country'];
									$tag = $this->findOrCreateTag($country, $tag_group);
									$p->tags()->syncWithoutDetaching($tag);
									
									#Region tag
									$tag_group = $this->tag_groups['Region'];
									$tag = $this->findOrCreateTag($region, $tag_group);
									$p->tags()->syncWithoutDetaching($tag);
									
									#Sub Region tag
									if($subregion){
										$tag_group = $this->tag_groups['Sub Region'];
										$tag = $this->findOrCreateTag($subregion, $tag_group);
										$p->tags()->syncWithoutDetaching($tag);
									}
									
									#Colour tag
									$tag_group = $this->tag_groups['Colour'];
									$tag = $this->findOrCreateTag($colour, $tag_group);
									$p->tags()->syncWithoutDetaching($tag);
									
									#Vintage tag
									$tag_group = $this->tag_groups['Vintage'];
									$tag = $this->findOrCreateTag($vintage, $tag_group);
									$p->tags()->syncWithoutDetaching($tag);
									
									#Burgundy Cru tag
									if($burgundy_cru){
										$tag_group = $this->tag_groups['Burgundy Cru'];
										$tag = $this->findOrCreateTag($burgundy_cru, $tag_group);
										$p->tags()->syncWithoutDetaching($tag);
									}
									
									
									$order_guid = $market['depth']['offers']['offer'][0]['orderGUID'];
									
									$tag_group = $this->tag_groups['Liv-Ex Order GUID'];
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
											$variant->attributes()->syncWithoutDetaching([$this->attributes['Duty Paid'] => ['sort' => $variant->attributes()->count()]]);
										}
										else{
											$variant->attributes()->syncWithoutDetaching([$this->attributes['Bond'] => ['sort' => $variant->attributes()->count()]]);
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
											'currency_code' => $this->currency->code,
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
									$img->handlePlaceholderImage($p);
								}
								else{
									$create_p_failed++;
								}
							}
							
							#add product to array for reindexing
							$this->addToProducts($p);
							
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
				
				#force reindexing
				$this->checkIndexing(true);
				
				Log::debug("Search API complete");
				Log::debug("created products $created_p/{$this->count} | created variants $created_v/{$this->count} | failed products $create_p_failed/{$this->count} | failed variants $create_v_failed/{$this->count} | updated $updated/{$this->count} | update failed $update_failed/{$this->count} | ignored $error/{$this->count}");
				
			} #end check for search response
		}
		else{
			Log::warning(json_encode($this->responsedata));
		}
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
     * Add a product to the queue to be indexed.
     *
     * @param $product
     */
    protected function addToProducts($product): void
    {
        if ($product) {
            if ($product->wasRecentlyCreated) {
                $product->wasRecentlyCreated = false;
                $this->products['created'][$product->id] = $product;
            } else {
                $this->products['updated'][$product->id] = $product;
            }
        }
    }

    /**
     * Check stored products to index.
     *
     * @param bool $force
     */
    protected function checkIndexing($force = false): void
    {
        if ($force || count($this->products['created']) > 5) {
            foreach ($this->products['created'] as $key => $product) {
                event(new ProductCreated($product));
                unset($this->products['created'][$key]);
            }

            $this->products['created'] = [];
        }

        if ($force || count($this->products['updated']) > 5) {
            foreach ($this->products['updated'] as $key => $product) {
                event(new ProductUpdated($product));
                unset($this->products['updated'][$key]);
            }

            $this->products['updated'] = [];
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
