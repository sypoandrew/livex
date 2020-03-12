<?php

namespace Sypo\Livex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Aero\Catalog\Models\Product;
use Aero\Catalog\Models\Tag;

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
     * Heartbeat API – Check that Liv-ex is up and available
     *
     * @return void
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
			
			Log::debug('heartbeat 1');
			Log::debug($status_code);
			Log::debug($res);
			#Log::debug($res->status);
		}
		catch(RequestException $e) {
			Log::debug($e);
		}
    }

    /**
     * Active Market API – Receive active bid and offer information for any wine in the Liv-ex database
     *
     * @return void
     */
    public function active_market()
    {
        $url = $this->base_url . 'exchange/v4/activeMarket';
        $headers = [
			'CLIENT_KEY' => $this->get_client_key(),
			'CLIENT_SECRET' => $this->get_client_secret(),
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		
		$params = [
			'lwin' => 'LWIN18', #LWIN11/LWIN16/LWIN18
			'priceType' => 'all', #all/bid/offer/list
			'contractType' => 'all', #all/SIB/SEP/X
			'bidFullDepth' => false, #true/false
			'offerFullDepth' => false, #true/false
			'listFullDepth' => false, #true/false
			'timeframe' => 'current', #current/15/30/45/90
			'currency' => 'gbp',
			'forexType' => 'spread', #spread/spot
		];
		
		
        $client = new \GuzzleHttp\Client();
		$client->setDefaultOption('headers', $headers);
		$response = $client->request('GET', $url, $params);

		$status_code = $response->getStatusCode(); // 200
		$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
		$res = $response->getBody();
		
		
		
		
		/* Magento code to convert below */

		/* 
		try {
			//Parse the XML FEED and process the data
			$xml = new SimpleXMLElement($data);
			$str = '';
			$warningMsg = '';
			
			$itotal = 0;
			$i = 0;
			$created = 0;
			$updated = 0;
			$error = 0;
			$vinquinnStockItem = 0;
			
			if($xml){
				
				$btl_size = $this->getAttributes('bottle_size');
				$vintage_r = $this->getAttributes('vintage');
				$country_r = $this->getAttributes('country_of_produce');
				$region_r = $this->getAttributes('region');
				$color_r = $this->getAttributes('color');
				$pack_size_r = $this->getAttributes('pack_size');
				$wine_type_r = $this->getAttributes('wine_type');
				$duty_status_r = $this->getAttributes('duty_status');
				#Mage::log($btl_size);
				#Mage::log($vintage_r);
				#Mage::log($country_r);
				#Mage::log($region_r);
				#Mage::log($pack_size_r);
				#Mage::log($wine_type_r);
				#Mage::log($duty_status_r);
				
				
				$categoryIds = array(34,45); //'show all wines' category
				$attributeSet = 10; //wine
				$websiteId = 2; //vinquinn
				
				//loop offers
				#foreach ($xml->liveOffer as $element) {
				foreach ($xml->liveExcel2Offer as $element) {
					$itotal++;
					
					#echo "lwin " . $element->lwin . "<br>";
					#echo "region " . $element->region . "<br>";
					#echo "unitSize " . $element->unitSize . "<br>";
					#echo "vintage " . $element->vintage . "<br>";
					#echo "wineName " . $element->wineName . "<br>";
					#echo "contractId " . $element->contractId . "<br>";
					#echo "lastChangeOn " . $element->lastChangeOn . "<br>";
					#echo "livexWineCode " . $element->livexWineCode . "<br>";
					#echo "price " . $element->price . "<br>";
					echo "qty " . $element->qty . "<br>";
					#echo "yourWineId " . $element->yourWineId . "<br>";
					#
					#die;
					
					
					
					try{
						unset($product);
						
						#################################
						//variables...
						$_sku = "{$element->lwin}IB"; //always in-bond
						$_region = (string) $element->region; //region can be sent as a country or a region
						$_unitSize = $element->unitSize;
						$_vintage = (string) $element->vintage;
						$_name = $element->wineName;
						$_price = $element->price;
						$_qty = (int) $element->qty;
						//Liv-Ex
						$_contractId = $element->contractId;
						$_lastChangeOn = $element->lastChangeOn;
						$_livexWineCode = $element->livexWineCode;
						$_yourWineId = $element->yourWineId;
						
						$_minimumQty = 0;
						if($element->specialInfo->minimumQty){
							$_minimumQty = $element->specialInfo->minimumQty;
						}
						$_dutyStatus = $duty_status_r["IB"];
						if($element->specialInfo->dutyPaid){
							$_sku = "{$element->lwin}DP";
							$_dutyStatus = $duty_status_r["DP"];
						}
						$_use_config_min_sale_qty = 1;
						if($_minimumQty > 1){
							$_use_config_min_sale_qty = 0;
						}
						#################################
						
						if($marginMarkup){
							$_price = ceil($_price * (1 + ($marginMarkup / 100)));
						}
						
						$hasError = false;
						
						$_contractId_lastChar = substr($_contractId, -1);
						
						#split processing into 5 runs to reduce server load.
						
						if(
						($feed_i == 1 and $_contractId_lastChar == 1) or 
						($feed_i == 2 and $_contractId_lastChar == 2) or 
						($feed_i == 3 and $_contractId_lastChar == 3) or 
						($feed_i == 4 and $_contractId_lastChar == 4) or 
						($feed_i == 5 and $_contractId_lastChar == 5) or 
						($feed_i == 6 and $_contractId_lastChar == 6) or 
						($feed_i == 7 and $_contractId_lastChar == 7) or 
						($feed_i == 8 and $_contractId_lastChar == 8) or 
						($feed_i == 9 and $_contractId_lastChar == 9) or 
						($feed_i == 10 and $_contractId_lastChar == 0)  
						){
							$i++;
							
							$packSize = '';
							$bottleSize = '';
							if($_unitSize){
								if(strpos($_unitSize, "x") !== false){
									$bottleSizex = explode("x", $_unitSize);
									
									$packSize = $bottleSizex[0];
									$bottleSize = $bottleSizex[1];
									
									#convert to ml
									if(substr($bottleSize, -2) == "cl"){
										$bottleSize_ml = substr($bottleSize, 0, -2);
										$bottleSize_ml = 10 * $bottleSize_ml;
										
										#check if greater than 1 litre
										$bottleSize_l = $bottleSize_ml / 1000;
										if($bottleSize_l >= 1){
											#string as litres
											$bottleSize_l = number_format($bottleSize_l, 1);
											$bottleSize = "{$bottleSize_l}l";
										}
										else{
											#string as millilitres
											$bottleSize = "{$bottleSize_ml}ml";
										}
									}
								}
							}
							
							if($bottleSize){
								if(!isset($btl_size[$bottleSize])){
									#$str .= " **NOT FOUND** ({$_unitSize}) ";
									$warningMsg .= "**WARNING** Bottle size not found: {$_unitSize} <br>";
									$hasError = true;
								}
							}
							
							if($packSize){
								if(!isset($pack_size_r[$packSize])){
									#$str .= " **NOT FOUND** ({$_unitSize}) ";
									$warningMsg .= "**WARNING** Pack size size not found: {$packSize} <br>";
									$hasError = true;
								}
							}
							
							if($stockThreshold){
								#dont import qty less than threshold
								if($_qty <= $stockThreshold){
									$hasError = true;
								}
							}
							
							if($priceThreshold){
								#dont import qty less than threshold
								if($_price <= $priceThreshold){
									$hasError = true;
								}
							}
							
							if($hasError){
								$error++;
							}
							else{
								$doUpdate = false;
								$product = false;
								
								if($_productId = Mage::getModel('catalog/product')->getIdBySku("LX$_sku")){
									##################################
									//update existing liv-ex product
									##################################
									
									$product = Mage::getModel('catalog/product')->load($_productId);
									
									$doUpdate = true;
								}
								elseif($_productId = Mage::getModel('catalog/product')->getIdBySku($_sku)){
									
									$product = Mage::getModel('catalog/product')->load($_productId);
									
									if($product->getLivexContractid() != ''){
										##################################
										//update existing liv-ex product
										##################################
										$doUpdate = true;
										
										#NOTE: we shouldnt get to this point, but lets handle it anyway just in case, and report possible issue
										$warningMsg .= "{$_sku} existing liv-ex product? {$product->getSku()}<br>";
									}
									else{
										##################################
										//Vinquinn stock item - dont process
										##################################
										$vinquinnStockItem++;
									}
								}
								else{
									##################################
									//create a new product...
									##################################
									
									Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
									
									$product = Mage::getModel('catalog/product');
									
									$product
										->setStoreId(1) //you can set data in store scope
										->setWebsiteIds(array($websiteId)) //website ID the product is assigned to, as an array
										->setAttributeSetId($attributeSet)
										->setTypeId('simple') //product type
										->setCreatedAt(strtotime('now')) //product creation time
										->setSku("LX$_sku") //SKU
										->setName("$_name $_vintage") //product name
										->setWeight(1.0000)
										->setStatus(2) //product status (1 - enabled, 2 - disabled)
										->setTaxClassId(0) //tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping)
										->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH) //catalog and search visibility
										->setPrice($_price)
										->setDescription("$_name <br>Case size:$_unitSize <br>Region:$_region <br>Vintage:$_vintage")
										->setShortDescription("$_name $_unitSize")
										->setStockData(array(
															'use_config_manage_stock' => 0, //'Use config settings' checkbox
															'manage_stock'=>1, //manage stock
															'is_in_stock' => 1, //Stock Availability
															'min_sale_qty' => $_minimumQty, //Minimum Qty Allowed in Shopping Cart
															'use_config_min_sale_qty' => $_use_config_min_sale_qty, //Minimum Qty Allowed in Shopping Cart
															'qty' => $_qty //qty
														)
										)
										->setCategoryIds($categoryIds); //assign product to categories
									
									$product->setData('bottle_size', $btl_size[$bottleSize]);
									
									$product->setData('duty_status', $_dutyStatus);
									
									$arr = $this->getDetailsFromRegionAbbreviation($_region);
									$_countryname = $arr['country'];
									$_colour = $arr['colour'];
									$_regionRealName = $arr['region'];
									
									
									//do some assumptions...
									//----------
									if($_countryname == 'Portugal'){
										$product->setData('wine_type', $wine_type_r["Fortified"]);
									}
									elseif($_regionRealName == 'Champagne'){
										$product->setData('wine_type', $wine_type_r["Sparkling"]);
									}
									else{
										$product->setData('wine_type', $wine_type_r["Still"]);
									}
									//----------
									
									if($_countryname){
										if(isset($country_r[$_countryname])){
											$product->setData('country_of_produce', $country_r[$_countryname]);
										}
									}
									if($_colour){
										if(isset($color_r[$_colour])){
											$product->setData('color', $color_r[$_colour]);
										}
									}
									if($packSize){
										if(isset($pack_size_r[$packSize])){
											$product->setData('pack_size', $pack_size_r[$packSize]);
										}
									}
									if($_regionRealName){
										if(isset($region_r[$_regionRealName])){
											$product->setData('region', $region_r[$_regionRealName]);
										}
									}
									
									if(isset($vintage_r[$_vintage])){
										$product->setData('vintage', $vintage_r[$_vintage]);
									}
									
									$product->setLivexContractid($_contractId); //Liv-ex contractId
									$product->setLivexLastchangeon($_lastChangeOn); //Liv-ex last changed on
									$product->save();
									
									
									
									$created++;
								}
								
								
								if($doUpdate){
									##################################
									//just update the essentials...
									##################################
									
									Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
									
									
									if($product->getLivexContractid() != $_contractId){
										#$warningMsg .= "{$product->getSku()} - Different contract id: CURRENT: {$product->getLivexContractid()} NEW: {$_contractId}<br>";
									}
									if($product->getLivexLastchangeon() != $_lastChangeOn){
										#$warningMsg .= "{$product->getSku()} - Item change date: CURRENT: {$product->getLivexLastchangeon()} NEW: {$_lastChangeOn}<br>";
									}
									
									
									$product
										->setSku("LX$_sku") //SKU
										->setPrice($_price)
										->setStockData(array(
															'use_config_manage_stock' => 0, //'Use config settings' checkbox
															'manage_stock'=>1, //manage stock
															'is_in_stock' => 1, //Stock Availability
															'min_sale_qty' => $_minimumQty, //Minimum Qty Allowed in Shopping Cart
															'use_config_min_sale_qty' => $_use_config_min_sale_qty, //Minimum Qty Allowed in Shopping Cart
															'qty' => $_qty //qty
														)
										);
									$product->setLivexContractid($_contractId); //Liv-ex contractId
									$product->setLivexLastchangeon($_lastChangeOn); //Liv-ex last changed on
									$product->save();
									
									$updated++;
								}
								
								
							} //end if errors
							
						} //end if in split section
					}
					catch(Exception $e){
						Mage::log($e->getMessage());
					}
					
				} //end foreach
			} //end if xml not empty
			
			$successMsg = '';
			$successMsg .= "** PROCESSING BATCH #$feed_i **<br>";
			$successMsg .= "Total items in feed: $itotal rows. <br>";
			$successMsg .= "Processed $i rows. <br>";
			$successMsg .= "Created $created/$i <br>";
			$successMsg .= "Updated $updated/$i <br>";
			$successMsg .= "Ignore $vinquinnStockItem/$i VinQuinn stock items<br>";
			$successMsg .= "Skipped $error/$i products (check warnings above for any errors if present)<br>";
			#$successMsg .= "Cleared stock of ".count($this->pids)." previous Liv-Ex products that were not found on latest feed<br>";
			
			
			#clear the feed file if last batch run
			if($feed_i == 10){
				
				$successMsg .= "ALL BATCHES NOW PROCESSED. Proceed to step 3.<br>";
				
				$filePath = $this->feedDir . DS . $this->feedFilename;
				
				$handle = fopen($filePath, "w");
				if($handle){
					fwrite($handle, '');
					fclose($handle);
				}
			}
			
			Mage::log(__FUNCTION__ . ' finished batch #' . $feed_i, null, $this->logFilename);
			
			if($setSessionVar){
				$feed_i++;
				
				#reset batch number back to 0
				if($feed_i > 10){
					$feed_i = 0;
				}
				Mage::getSingleton('core/session')->setLivExFeedIteration($feed_i);
			}
			
			return array(
				"success" => $successMsg,
				"warning" => $warningMsg
				);
		}
		catch (Exception $e) {
			#echo "Exception";
			#echo $e->getMessage();
			Mage::logException($e);
		}
		 */
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
			'lwin' => 'LWIN18', #LWIN11/LWIN16/LWIN18
			'currency' => 'gbp',
			'minPrice' => 0,
			'maxPrice' => 0,
		];
		
		
        $client = new \GuzzleHttp\Client();
		$client->setDefaultOption('headers', $headers);
		$response = $client->request('GET', $url, $params);

		$status_code = $response->getStatusCode(); // 200
		$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
		$res = $response->getBody();
    }
}
