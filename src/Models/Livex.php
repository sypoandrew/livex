<?php

namespace Sypo\Livex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Aero\Catalog\Models\Product;
use Aero\Catalog\Models\Variant;
use Aero\Catalog\Models\Tag;
use Aero\Catalog\Models\TagGroup;

class Livex extends Model
{
    protected $environment;
    protected $base_url;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
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
     * Get the client API key
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
     * Get all tags for a specified group
     *
     * @return array
     */
    public function get_tags_by_group($group)
    {
		$tags = Tag::select('tag_groups.name as tag_group', 'tags.name as value')->join('tag_groups', 'tag_groups.id', '=', 'tags.tag_group_id')->where('tag_groups.name', 'like', '%'.$group.'%')->get();
		
		$arr = [];
		foreach($tags as $t){
			$tag_group = json_decode($t->tag_group);
			$tag_value = json_decode($t->value);
			$arr[$tag_value->en] = $tag_value->en;
		}
		Log::debug($arr);
		return $arr;
    }

    /**
     * Heartbeat API – Check that Liv-ex is up and available
     *
     * @return void
     */
    public static function get_tag_groups()
    {
		$groups = TagGroup::whereIn('name->en', ['Bottle Size', 'Case Size', 'Colour', 'Country', 'Region', 'Sub Region', 'Vintage', 'Wine Type', 'Burgundy Cru'])->get();
		
		$arr = [];
		foreach($groups as $g){
			$arr[$g->name] = $g->id;
		}
		#Log::debug($arr);
		return $arr;
    }

    /**
     * Heartbeat API – Check that Liv-ex is up and available
     *
     * @return void
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
			$res = $response->getBody();
			
			Log::debug('heartbeat');
			Log::debug($status_code);
			Log::debug($res);
			#Log::debug($res->status);
		}
		catch(RequestException $e) {
			Log::debug($e);
		}
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
		$res = $response->getBody();
		
		Log::debug(__FUNCTION__);
		#Log::debug($status_code);
		Log::debug($res);
		
		$groups = self::get_tag_groups();
		
		
		if($body = $response->getBody()){
			$data = json_decode($response->getBody(), true);
			
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
						#Log::debug($item);
						
						$i++;
						
						try{
							
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

								$p = Product::where('model', 'LX'.$sku)->first();
								if($p != null){
									#already on system - just update the essentials
									Log::debug('update the variant LX'.$sku);
									
									if($dutyPaid){
										$variant = Variant::where('product_id', $p->id)->where('sku', 'like', '%DP')->first();
									}
									else{
										$variant = Variant::where('product_id', $p->id)->where('sku', 'like', '%IB')->first();
									}
									
									$variant->stock_level = $market['depth']['offers']['offer'][0]['quantity'];
									$variant->minimum_quantity = ($minimumQty) ? $minimumQty : 0;
									/* if($variant->save()){
										$updated++;
									}
									else{
										$update_failed++;
									} */
								}
								else{
									#not currently on system - create it
									Log::debug('create LX'.$sku);
									
									$pack_size = $market['packSize'];
									$bottle_size = $market['bottleSize']; #data in zero-padded millilitres e.g. 00750
									$bottle_size = self::format_bottle_size($bottle_size);
									Log::debug($bottle_size);
									
									
									$p = new Product;
									$p->sku = 'LX'.$sku;
									$p->name = $name;
									$p->summary = ['en' => $name];
									$p->description = ['en' => $name];
									
									Log::debug('groupid:');
									#dd(TagGroup::where('name->en', 'Bottle Size')->first('id')->id);
									#dd(TagGroup::select('id')->where('name->en', 'Burgundy Cru')->first()->id);
									
									/* 
									if($p->save()){
										$created_p++;
										
										
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
										
										#$tag_group_id = TagGroup::select('id')->where('name->en', 'Bottle Size')->first()->id;
										$tag_group_id = $groups['Bottle Size'];
										$t = Tag::where('tag_group_id', $tag_group_id)->where('name->en', $bottle_size)->first();
										if($t === null){
											$t = new Tag;
											$t->name = ['en' => $bottle_size];
											$t->tag_group_id = $tag_group_id;
											$t->save();
											
											#link tag to product
											$p->tag($t);
										}
										else{
											#link tag to product
											$p->tag($t);
										}
										
										#Pack Size tag
										#$tag_group_id = TagGroup::select('id')->where('name->en', 'Pack Size')->first()->id;
										$tag_group_id = $groups['Pack Size'];
										$t = Tag::where('tag_group_id', $tag_group_id)->where('name->en', $pack_size)->first();
										if($t === null){
											$t = new Tag;
											$t->name = ['en' => $pack_size];
											$t->tag_group_id = $tag_group_id;
											$t->save();
											
											#link tag to product
											$p->tag($t);
										}
										else{
											#link tag to product
											$p->tag($t);
										}
										
										
										#Wine Type tag
										#$tag_group_id = TagGroup::select('id')->where('name->en', 'Wine Type')->first()->id;
										$tag_group_id = $groups['Wine Type'];
										$t = Tag::where('tag_group_id', $tag_group_id)->where('name->en', $wine_type)->first();
										if($t === null){
											$t = new Tag;
											$t->name = ['en' => $wine_type];
											$t->tag_group_id = $tag_group_id;
											$t->save();
											
											#link tag to product
											$p->tag($t);
										}
										else{
											#link tag to product
											$p->tag($t);
										}
										
										#Country tag
										#$tag_group_id = TagGroup::select('id')->where('name->en', 'Country')->first()->id;
										$tag_group_id = $groups['Country'];
										$t = Tag::where('tag_group_id', $tag_group_id)->where('name->en', $country)->first();
										if($t === null){
											$t = new Tag;
											$t->name = ['en' => $country];
											$t->tag_group_id = $tag_group_id;
											$t->save();
											
											#link tag to product
											$p->tag($t);
										}
										else{
											#link tag to product
											$p->tag($t);
										}
										
										#Region tag
										#$tag_group_id = TagGroup::select('id')->where('name->en', 'Region')->first()->id;
										$tag_group_id = $groups['Region'];
										$t = Tag::where('tag_group_id', $tag_group_id)->where('name->en', $region)->first();
										if($t === null){
											$t = new Tag;
											$t->name = ['en' => $region];
											$t->tag_group_id = $tag_group_id;
											$t->save();
											
											#link tag to product
											$p->tag($t);
										}
										else{
											#link tag to product
											$p->tag($t);
										}
										
										if($subregion){
											#$tag_group_id = TagGroup::select('id')->where('name->en', 'Sub Region')->first()->id;
											$tag_group_id = $groups['Sub Region'];
											$t = Tag::where('tag_group_id', $tag_group_id)->where('name->en', $subregion)->first();
											if($t === null){
												$t = new Tag;
												$t->name = ['en' => $subregion];
												$t->tag_group_id = $tag_group_id;
												$t->save();
												
												#link tag to product
												$p->tag($t);
											}
											else{
												#link tag to product
												$p->tag($t);
											}
										}
										
										
										#Colour tag
										#$tag_group_id = TagGroup::select('id')->where('name->en', 'Colour')->first()->id;
										$tag_group_id = $groups['Colour'];
										$t = Tag::where('tag_group_id', $tag_group_id)->where('name->en', $colour)->first();
										if($t === null){
											$t = new Tag;
											$t->name = ['en' => $colour];
											$t->tag_group_id = $tag_group_id;
											$t->save();
											
											#link tag to product
											$p->tag($t);
										}
										else{
											#link tag to product
											$p->tag($t);
										}
										
										#Vintage tag
										#$tag_group_id = TagGroup::select('id')->where('name->en', 'Vintage')->first()->id;
										$tag_group_id = $groups['Vintage'];
										$t = Tag::where('tag_group_id', $tag_group_id)->where('name->en', $vintage)->first();
										if($t === null){
											$t = new Tag;
											$t->name = ['en' => $vintage];
											$t->tag_group_id = $tag_group_id;
											$t->save();
											
											#link tag to product
											$p->tag($t);
										}
										else{
											#link tag to product
											$p->tag($t);
										}
										
										if($burgundy_cru){
											$t = new Tag;
											$t->name = ['en' => $burgundy_cru];
											$t->tag_group_id = TagGroup::select('id')->where('name->en', 'Burgundy Cru')->first()->id;
											$t->save();
											
											#link tag to product
											$p->tag($t);
										}
										
										
										
										
										
										
										
										
										$variant = new Variant;
										$variant->product_id = $p->id;
										$variant->stock_level = $market['depth']['offers']['offer'][0]['quantity'];
										$variant->minimum_quantity = ($minimumQty) ? $minimumQty : 0;
										$variant->sku = $p->sku.'IB';
										if($dutyPaid){
											$variant->sku = $p->sku.'DP';
										}
										if($variant->save()){
											$created_v++;
										}
										else{
											$create_v_failed++;
										}
									}
									else{
										$create_p_failed++;
									}
									 */
									
								}
							}
							
							
							
							
							
							
							
							
							
							
							
							
						}
						catch(ErrorException  $e){
							Log::debug($e);
						}
						catch(Exception $e){
							
						}
						
						
					} #end search response loop
				} #end check for search response
			} #end check for OK status
		}
    }
	
	public static function format_bottle_size($bottle_size){
		$size = (int) $bottle_size;
		if($size < 1000){
			return $size.'ml';
		}
		return number_format(($size / 1000), 1).'l';
	}
}
