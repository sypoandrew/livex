<?php

namespace Sypo\Livex\Models;

use Illuminate\Support\Facades\Log;
use Aero\Cart\Models\Order;
use Sypo\Livex\Models\LivexAPI;
use Sypo\Livex\Models\OrderStatusAPI;
use Sypo\Livex\Models\Helper;

class OrderAPI extends LivexAPI
{
    protected $error_code = 'order_api';
    
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
		$proceed_with_order = $order_status->call($order, true);
		$order_guids = $order_status->get_order_guids();
		$response = $order_status->get_responsedata();
		$dets = (isset($response['orderStatus']['status'])) ? $response['orderStatus']['status'] : array();
		#dd($dets);
		
		if($proceed_with_order){
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
										#Log::warning(json_encode($this->responsedata));
										
										$err = new ErrorReport;
										$err->message = json_encode($this->responsedata);
										$err->code = $this->error_code;
										$err->line = __LINE__;
										$err->order_id = $order->id;
										$user = \Auth::user();
										if($user){
											$err->admin_id = $user->id;
										}
										$err->save();
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
								$user = \Auth::user();
								if($user){
									$err->admin_id = $user->id;
								}
								$err->save();
							}
						}
						else{
							#Log::warning('unable to post order line '.$item->sku.' to Liv-ex');
							
							$err = new ErrorReport;
							$err->message = 'Unable to post order line '.$item->sku.' to Liv-ex.';
							$err->code = $this->error_code;
							$err->line = __LINE__;
							$err->order_id = $order->id;
							$user = \Auth::user();
							if($user){
								$err->admin_id = $user->id;
							}
							$err->save();
						}
					}
				}
			}
			else{
				#Log::warning('Unable to post order '.$order->id.' to Liv-ex. No items in order qualify.');
				
				$err = new ErrorReport;
				$err->message = 'Unable to post order '.$order->id.' to Liv-ex. No items in order qualify.';
				$err->code = $this->error_code;
				$err->line = __LINE__;
				$err->order_id = $order->id;
				$user = \Auth::user();
				if($user){
					$err->admin_id = $user->id;
				}
				$err->save();
			}
		}
		elseif($order_guids){
			#Log::warning('Unable to post order '.$order->id.' to Liv-ex');
			
			$err = new ErrorReport;
			$err->message = 'Unable to post order '.$order->id.' to Liv-ex. This may be issue with items failing pre-order checks, or an issue with the OrderStatusAPI call as there are qualifying items. Manual check required.';
			$err->code = $this->error_code;
			$err->line = __LINE__;
			$err->order_id = $order->id;
			$user = \Auth::user();
			if($user){
				$err->admin_id = $user->id;
			}
			$err->save();
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
					#Log::warning(json_encode($this->responsedata));
					
					$err = new ErrorReport;
					$err->message = json_encode($this->responsedata);
					$err->code = $this->error_code;
					$err->line = __LINE__;
					$err->order_id = $order->id;
					$user = \Auth::user();
					if($user){
						$err->admin_id = $user->id;
					}
					$err->save();
				}
			}
		}
		
		return false;
    }
}
