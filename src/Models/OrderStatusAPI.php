<?php

namespace Sypo\Livex\Models;

use Illuminate\Support\Facades\Log;
use Aero\Catalog\Models\Tag;
use Aero\Catalog\Models\Variant;
use Sypo\Livex\Models\LivexAPI;

class OrderStatusAPI extends LivexAPI
{
    protected $error_code = 'order_status_api';
    protected $order_guids;
    protected $errors; # user friendly error reporting
    
	/**
     * Get Liv-ex Order GUIDs and line info from the order items
     *
     * @param \Aero\Cart\Models\Order or \Aero\Cart\Cart $order
     * @return array
     */
    public function get_eligable_items($order){
		$dets = [];
		$this->order_guids = [];
		if($order instanceof \Aero\Cart\Cart) {
			$items = $order->items();
			#dd($items);
			foreach($items as $item){
				if(substr($item->sku, 0, 2) == 'LX'){
					$guid = $item->model->product()->first()->additional('livex_order_guid');
					if($guid){
						$dets[$guid] = ['sku' => $item->sku, 'qty' => $item->quantity];
					}
					else{
						$this->errors[] = $item->name . ' is currently unavailable from our suppliers.';
					}
				}
			}
		}
		elseif($order instanceof \Aero\Cart\Models\Order){
			$items = $order->items()->where('sku', 'like', 'LX%')->get();
			if($items != null){
				foreach($items as $item){
					$guid = $item->buyable()->first()->product()->first()->additional('livex_order_guid');
					if($guid){
						$dets[$guid] = ['sku' => $item->sku, 'qty' => $item->quantity];
					}
					else{
						$this->errors[] = $item->name . ' is currently unavailable from our suppliers.';
						
						$err = new ErrorReport;
						$err->message = $item->sku . ' does not have guid attached to product';
						$err->code = $this->error_code;
						$err->line = __LINE__;
						$err->order_id = $order->id;
						$err->save();
					}
				}
			}
		}
		
		#dd($dets);
		return $dets;
	}

    /**
     * Order Status API – check status of offers in basket (prior to checkout payment)
     *
     * @param \Aero\Cart\Models\Order or \Aero\Cart\Cart $order
     * @return boolean
     */
    public function call($order)
    {
		$proceed_with_order = true;
		
        $url = $this->base_url . 'exchange/v1/orderStatus';
		
		#get the Liv-ex order GUIDs from the Aero order items
		$item_info = $this->get_eligable_items($order);
		#dd($item_info);
		$this->order_guids = array_keys($item_info);
		#dd($this->order_guids);
		
		$order_line_count = 0;
		if($order instanceof \Aero\Cart\Cart) {
			$order_id = null;
			
			$order_line_count = $order->items()->filter(function ($item, $key) {
				return substr($item->sku, 0, 2) == 'LX';
			})->count();
		}
		else{
			$order_line_count = $order->items()->where('sku', 'like', 'LX%')->count();
			
			$order_id = $order->id;
		}
		
		#dd("order_line_count $order_line_count item_info ".count($item_info));
		if($order_line_count != count($item_info)){
			#some LX products do not have a guid attached - do not allow to order
			$proceed_with_order = false;
		}
		elseif($this->order_guids){
			#report each item individually for more granular error reporting
			foreach($this->order_guids as $guid){
				$params = [
					'orderGUID' => [$guid],
				];

				#$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params, 'debug' => true]);
				$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params]);
				$this->set_responsedata();

				#Log::debug($this->responsedata);
				if($this->responsedata['status'] == 'OK'){
					#dd($this->responsedata);
					foreach($this->responsedata['orderStatus']['status'] as $order_status){
						#check if able to proceed with Aero order here...
						if($order_status['orderStatus'] ==  'S' or $order_status['orderStatus'] ==  'T'){
							#offer has been suspended or Traded - stop user from progressing through checkout
							$proceed_with_order = false;
							
							$err = new ErrorReport;
							$err->message = 'Offer '.$item_info[$order_status['orderGUID']]['sku'].' has been Suspended or Traded';
							$err->code = $this->error_code;
							$err->line = __LINE__;
							$err->order_id = $order_id;
							$err->save();
							
							$product_name = Variant::where('sku', $item_info[$guid]['sku'])->first()->product()->first()->name;
							$this->errors[] = $product_name . ' is currently unavailable from our suppliers.';
						}
						elseif($order_status['quantity'] < $item_info[$order_status['orderGUID']]['qty']){
							#offer has less qty available than user has in basket - stop user from progressing through checkout
							$proceed_with_order = false;
							
							$err = new ErrorReport;
							$err->message = 'Offer '.$item_info[$order_status['orderGUID']]['sku'].' has less qty available than user has in basket';
							$err->code = $this->error_code;
							$err->line = __LINE__;
							$err->order_id = $order_id;
							$err->save();
							
							$product_name = Variant::where('sku', $item_info[$guid]['sku'])->first()->product()->first()->name;
							$this->errors[] = 'Only ' . $order_status['quantity'] . ' x ' . $product_name . ' are currently available from our suppliers.';
						}
					}
				}
				else{
					$err = new ErrorReport;
					$err->message = json_encode($this->responsedata);
					$err->code = $this->error_code;
					$err->line = __LINE__;
					$err->order_id = $order_id;
					$err->save();
					
					if(isset($this->responsedata['error']) and $this->responsedata['error']['code'] == 'V056'){
						#GUID is not available or does not exist - removed from Liv-ex so prevent customer from proceeding
						$proceed_with_order = false;
						
						$product_name = Variant::where('sku', $item_info[$guid]['sku'])->first()->name;
						$this->errors[] = $product_name . ' is currently unavailable from our suppliers.';
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
	
	public function get_order_guids(){
		return $this->order_guids;
	}
	
	public function get_errors(){
		return $this->errors;
	}
}
