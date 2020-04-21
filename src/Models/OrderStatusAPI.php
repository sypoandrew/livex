<?php

namespace Sypo\Livex\Models;

use Illuminate\Support\Facades\Log;
use Aero\Catalog\Models\Tag;
use Sypo\Livex\Models\LivexAPI;

class OrderStatusAPI extends LivexAPI
{
    protected $error_code = 'order_status_api';
    protected $order_guids;
    
	/**
     * Get Liv-ex Order GUIDs and line info from the order items
     *
     * @param int $order_id
     * @return array
     */
    public function get_order_details($order_id){
		
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
    public function call(\Aero\Cart\Models\Order $order)
    {
		$proceed_with_order = true;
		
        $url = $this->base_url . 'exchange/v1/orderStatus';
		
		#get the Liv-ex order GUIDs from the Aero order items
		$order_details = $this->get_order_details($order->id);
		#dd($order_details);
		$item_info = [];
		foreach($order_details as $det){
			$item_info[$det->name] = ['sku' => $det->sku, 'qty' => $det->quantity];
		}
		#dd($item_info);
		$this->order_guids = array_keys($item_info);
		#dd($this->order_guids);
		#Log::debug($this->order_guids);
		
		if($this->order_guids){
			$params = [
				'orderGUID' => $this->order_guids,
			];

			#$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params, 'debug' => true]);
			$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params]);
			$this->set_responsedata();

			#Log::debug(__FUNCTION__);
			
			#Log::debug($this->responsedata);
			if($this->responsedata['status'] == 'OK'){
				#dd($this->responsedata);
				foreach($this->responsedata['orderStatus']['status'] as $order_status){
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
				#Log::warning(json_encode($this->responsedata));
				
				$err = new ErrorReport;
				$err->message = json_encode($this->responsedata);
				$err->code = $this->error_code;
				$err->line = __LINE__;
				$err->order_id = $order->id;
				$err->save();
				
				if(isset($this->responsedata['error']) and $this->responsedata['error']['code'] == 'V056'){
					#GUID is not available or does not exist - removed from Liv-ex so prevent customer from proceeding
					$proceed_with_order = false;
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
}
