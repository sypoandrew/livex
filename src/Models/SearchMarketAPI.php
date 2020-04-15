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
	protected $categories = [3, 5]; #Buy Wine | Liv-Ex wines
    public $result = ['count' => 0, 'i' => 0, 'created_p' => 0, 'created_v' => 0, 'create_p_failed' => 0, 'create_v_failed' => 0, 'updated' => 0, 'update_failed' => 0, 'error' => 0];
    public $items;
    /**
     * Storage of products that have been processed.
     *
     * @var array
     */
    protected $products = ['created' => [], 'updated' => []];
    protected $processed_items;
    protected $placeholder_image;


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
		$this->placeholder_image = new PlaceholderImage;
		
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
		
		#Log::debug($this->responsedata);
		#dd($this->responsedata);
		#Log::debug($this->responsedata['pageInfo']);
		if($this->responsedata['status'] == 'OK'){
			$this->result['count'] = $this->responsedata['pageInfo']['totalResults'];
			$this->result['i'] = 0;
			$this->result['created_p'] = 0;
			$this->result['created_v'] = 0;
			$this->result['create_p_failed'] = 0;
			$this->result['create_v_failed'] = 0;
			$this->result['updated'] = 0;
			$this->result['update_failed'] = 0;
			$this->result['error'] = 0;
			$this->processed_items = [];
			
			$img = new PlaceholderImage;
			
			if(isset($this->responsedata['searchResponse'])){
				#Log::debug($this->responsedata['searchResponse']);
				$this->result['count'] = count($this->responsedata['searchResponse']);
				$this->items = $this->responsedata['searchResponse'];
				
			} #end check for search response
		}
		else{
			Log::warning(json_encode($this->responsedata));
		}
    }

    /**
     * Process all items from Search API response
     *
     * @return void
     */
    public function process_all()
    {
		$this->call();
		foreach($this->items as $item){
			$this->process_item($item);
		}
		$this->cleanup();
    }

    /**
     * Handle reindexing post-processing
     *
     * @return void
     */
    public function cleanup()
    {
		#zero all other Livex stock that wasn't on the feed
		#dd($this->processed_items);
		#dd(Variant::where('sku', 'like', 'LX%')->where('stock_level', '>', 0)->whereNotIn('product_id', $this->processed_items)->toSql());
		$zero_stock_items = [];
		$zero_stock = Variant::select('product_id')->where('sku', 'like', 'LX%')->where('stock_level', '>', 0)->whereNotIn('product_id', $this->processed_items)->get();
		foreach($zero_stock as $zero_stock_item){
			$zero_stock_items[$zero_stock_item->product_id] = $zero_stock_item->product_id;
			#add product to reindex routine
			$this->addToProducts($zero_stock_item->product()->first());
		}
		Variant::where('sku', 'like', 'LX%')->where('stock_level', '>', 0)->whereNotIn('product_id', $this->processed_items)->update(['stock_level' => 0]);
		
		#force reindexing
		$this->checkIndexing(true);
		
		Log::debug("Search API complete");
		Log::debug("created products {$this->result['created_p']}/{$this->result['count']} | created variants {$this->result['created_v']}/{$this->result['count']} | failed products {$this->result['create_p_failed']}/{$this->result['count']} | failed variants {$this->result['create_v_failed']}/{$this->result['count']} | updated {$this->result['updated']}/{$this->result['count']} | update failed {$this->result['update_failed']}/{$this->result['count']} | ignored {$this->result['error']}/{$this->result['count']}");
    }

    /**
     * Process item from Search API response
     *
     * @param array $item
     * @return void
     */
    public function process_item($item)
    {
		#Log::debug($item);
		#Log::debug(json_encode($item));
		
		$this->result['i']++;
		
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
				#$this->result['error']++;
				#Log::debug("ignore $sku no offers");
				continue;
			}
			$sku = $market['lwin']; #LWIN18
			$dutyPaid = $market['special']['dutyPaid']; #true/false
			$minimumQty = $market['special']['minimumQty'];
			$isCompetitive = $market['depth']['offers']['offer'][0]['isCompetitive'];
			
			$burgundy_cru = '';

			try{
				
				if($this->result['created_p'] >= 1){
					#die;
					#break;
				}
				
				if($market['depth']['offers']['offer'][0]['price'] < 250){
					#$this->result['error']++;
					#Log::debug("ignore $sku due to price {$market['depth']['offers']['offer'][0]['price']}");
					continue;
				}
				
				if($minimumQty > 1){
					#$this->result['error']++;
					#Log::debug("ignore $sku due to minimumQty {$minimumQty}");
					continue;
				}
				
				if($dutyPaid){
					#$this->result['error']++;
					#Log::debug("ignore $sku due to dutyPaid {$dutyPaid}");
					continue;
				}
				
				$p = Product::where('model', 'LX'.$sku)->first();
				if($p != null){
					#already on system - just update the essentials
					#Log::debug('update the variant LX'.$sku.' duty paid '.(int)$dutyPaid);
					#Log::debug($dutyPaid);
					#dd('update the variant LX'.$sku);
					
					$this->processed_items[] = $p->id;
					
					if(!$p->allImages()->count()){
						#Handle image placeholder
						#Log::debug($sku.' no image - add placeholder');
						$this->placeholder_image->handlePlaceholderImage($p);
					}
					
					#check for orderGUID tag
					$order_guid = $market['depth']['offers']['offer'][0]['orderGUID'];
					$tag_group = $this->tag_groups['Liv-Ex Order GUID'];
					$guid_tag = $p->tags()->where('tag_group_id', $tag_group->id)->first();
					if($guid_tag != null){
						#found tag - check if it's the same GUID
						if($order_guid == $guid_tag->name){
							#it's the same - no action required
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
						$tag = $this->findOrCreateTag($order_guid, $tag_group);
						$p->tags()->syncWithoutDetaching($tag);
					}
					
					$minimumQty = ($minimumQty) ? $minimumQty : 0;
					$price_updated = false;
					$items_updated = $p->variants()->update(['stock_level' => $market['depth']['offers']['offer'][0]['quantity'], 'minimum_quantity' => $minimumQty]);
					
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
								$price_updated = $price->update(['value' => $item_price_w_markup]);
								#Log::debug("{$p->id} {$in_bond_item->sku} variant price updated successfully new price {$item_price_w_markup}");
							}
						}
						else{
							#$this->result['update_failed']++;
							#Log::warning('variant '.$in_bond_item->sku.' failed to find price');
							
							#we shouldn't really get to here, but lets add the variant price
							$price = new Price([
								'variant_id' => $in_bond_item->id,
								'product_tax_group_id' => $in_bond_item->product_tax_group_id,
								'product_id' => $p->id,
								'quantity' => 1,
								'currency_code' => $this->currency->code,
							]);
							
							$item_price = $market['depth']['offers']['offer'][0]['price'];
							$item_price_w_markup = $this->calculate_item_price($item_price);
							#Log::debug($item_price);
							#Log::debug($item_price_w_markup);
							$price->value = $item_price_w_markup;
							
							if($price->save()){
								#Log::debug('variant price created successfully');
							}
							else{
								$this->result['update_failed']++;
								Log::warning('variant '.$in_bond_item->sku.' price failed to create');
							}
						}
					}
					else{
						#$this->result['update_failed']++;
						#Log::warning('product '.$p->model.' failed to find bond variant');
						
						#we shouldn't really get to here, but lets create the in-bond variant
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
							$this->result['created_v']++;
							
							#Log::debug('variant '.$variant->sku.' created successfully');
							
							#add the attribute for the variant Bond/Duty Paid
							if($dutyPaid){
								$variant->attributes()->syncWithoutDetaching([$this->attributes['Duty Paid'] => ['sort' => $variant->attributes()->count()]]);
							}
							else{
								$variant->attributes()->syncWithoutDetaching([$this->attributes['Bond'] => ['sort' => $variant->attributes()->count()]]);
							}
							
							if(!$variant->attributes()->count()){
								Log::warning('variant attribute failed to create');
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
							#Log::debug($item_price);
							#Log::debug($item_price_w_markup);
							$price->value = $item_price_w_markup;
							
							if($price->save()){
								#Log::debug('variant price created successfully');
							}
							else{
								$this->result['update_failed']++;
								Log::warning('variant '.$variant->sku.' price failed to create');
							}
						}
						else{
							$this->result['update_failed']++;
							Log::warning('variant '.$variant->sku.' failed to create');
						}
					}
					
					$this->result['updated']++;
				}
				else{
					#not currently on system - create it
					#Log::debug('create LX'.$sku);
					#dd('create LX'.$sku);
					
					$case_size = (int) $market['packSize'];
					$bottle_size = $market['bottleSize']; #data in zero-padded millilitres e.g. 00750
					$bottle_size = self::format_bottle_size($bottle_size);
					#Log::debug($bottle_size);
					
					$nameHTML = "<p>$name</p>";
					
					$p = new Product;
					$p->model = 'LX'.$sku;
					$p->name = $name;
					$p->summary = $nameHTML;
					$p->description = $name;
					$p->active = false; #initially hide - to be vetted prior to listing on website
					$p->type = 'variant';
					
					
					if($p->save()){
						$this->result['created_p']++;
						
						$this->processed_items[] = $p->id;
						
						#add into categories
						foreach($this->categories as $category_id){
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
							$this->result['created_v']++;
							
							#Log::debug('variant '.$variant->sku.' created successfully');
							
							#add the attribute for the variant Bond/Duty Paid
							if($dutyPaid){
								$variant->attributes()->syncWithoutDetaching([$this->attributes['Duty Paid'] => ['sort' => $variant->attributes()->count()]]);
							}
							else{
								$variant->attributes()->syncWithoutDetaching([$this->attributes['Bond'] => ['sort' => $variant->attributes()->count()]]);
							}
							
							if(!$variant->attributes()->count()){
								Log::warning('variant attribute failed to create');
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
							#Log::debug($item_price);
							#Log::debug($item_price_w_markup);
							$price->value = $item_price_w_markup;
							
							if($price->save()){
								#Log::debug('variant price created successfully');
							}
							else{
								Log::warning('variant price failed to create');
							}
						}
						else{
							$this->result['create_v_failed']++;
							
							Log::warning('variant '.$variant->sku.' failed to create');
						}
						
						#Handle image
						$this->placeholder_image->handlePlaceholderImage($p);
					}
					else{
						$this->result['create_p_failed']++;
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
    protected function findOrCreateTag($name, \Aero\Catalog\Models\TagGroup $group)
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
