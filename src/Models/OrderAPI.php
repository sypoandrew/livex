<?php

namespace Sypo\Livex\Models;

use Illuminate\Support\Facades\Log;
use Aero\Cart\Models\Order;
use Sypo\Livex\Models\LivexAPI;
use Sypo\Livex\Models\OrderStatusAPI;
use Sypo\Livex\Models\Helper;

class OrderAPI extends LivexAPI
{
    /**
     * Orders API – send order to Liv-ex (after checkout payment)
     *
     * @param \Aero\Cart\Models\Order $order
     * @return void
     */
    public function add(\Aero\Cart\Models\Order $order)
    {
		$url = $this->base_url . 'exchange/v6/orders';
		
		#get the item status from Liv-ex - this is in nice format to then handle the params to post the order
		$order_status = new OrderStatusAPI;
		$dets = $order_status->call($order, true);
		#dd($dets);
		
		if(is_array($dets) and count($dets) > 0){
			
			#Log::debug(__FUNCTION__);
			
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
							'merchantRef' => $order->reference,
							'overrideFatFinger' => true, #Bypass system checks that prevent price keying errors.
						];
						
						#we post each order line separately so we can correctly store GUID against the SKU
						
						$params = ['orders' => $order_det];
						#dd($params);
						#dd(json_encode($params));

						#$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params, 'debug' => true]);
						$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params]);
						$this->set_responsedata();

						#dd($this->responsedata);
						#Log::debug($this->responsedata);
						if($this->responsedata['status'] == 'OK'){
							foreach($this->responsedata['orders']['order'] as $order_data){
								if($order_data['orderGUID']){
									$order->additional('livex_guid_'.$item->sku, $order_data['orderGUID']);
								}
								
								if($order_data['errors']){
									Log::warning(json_encode($this->responsedata));
								}
							}
						}
						else{
							Log::warning(json_encode($this->responsedata));
						}
					}
					else{
						Log::warning('unable to post order line '.$item->sku.' to Livex');
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
    public function cancel(\Aero\Cart\Models\Order $order)
    {
		$guids = Helper::get_order_guids($order);
		if($guids != null){
			$url = $this->base_url . 'exchange/v6/orders';
			
			foreach($guids as $guid_data){
				
				$params = [
					'orderGUID' => $guid_data->value,
				];

				#$this->response = $this->client->request('DELETE', $url, ['headers' => $this->headers, 'json' => $params, 'debug' => true]);
				$this->response = $this->client->request('DELETE', $url, ['headers' => $this->headers, 'json' => $params]);
				$this->set_responsedata();
				
				#Log::debug(__FUNCTION__);
				
				if($this->responsedata['status'] == 'OK'){
					return true;
				}
				else{
					Log::warning(json_encode($this->responsedata));
				}
			}
		}
		
		return false;
    }
}
