<?php

namespace Sypo\Livex\Models;

use Illuminate\Support\Facades\Log;
use Aero\Cart\Models\Order;
use Sypo\Livex\Models\LivexAPI;
use Sypo\Livex\Models\HeartbeatAPI;
use Sypo\Livex\Models\OrderStatusAPI;
use Sypo\Livex\Models\Helper;
use Sypo\Livex\Models\ErrorReport;

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
		#dont send to liv-ex if cash payment method
		if($order->payment_methods->first()->driver != 'cash' or $this->environment == 'test'){
			#only send to Livex if order amount is less than threshold
			if($order->subtotalPrice->incValue < (setting('Livex.max_subtotal_in_basket') * 100)){
				
				$heartbeat = new HeartbeatAPI;
				$connection_ok = $heartbeat->call();
				if($connection_ok){
					$url = $this->base_url . 'exchange/v6/orders';
					
					#get the item statuses from Liv-ex
					$order_status = new OrderStatusAPI;
					$proceed_with_order = $order_status->call($order);
					$offer_guids = $order_status->get_offer_guids();
					$response = $order_status->get_responsedata();
					#$dets = (isset($response['orderStatus']['status'])) ? $response['orderStatus']['status'] : array();
					$dets = $order_status->get_responses();
					#dd($offer_guids);
					#dd($dets);
					
					if($proceed_with_order){
						if(is_array($dets) and count($dets) > 0){
							
							#Log::debug(__FUNCTION__);
							
							$orderItems = $order->items()->get();
							#dd($orderItems);
							
							#loop all order items
							foreach($orderItems as $item){
								$item_posted = false;
								#loop Liv-ex status responses
								foreach($dets as $iteminfo){
									$lwin18 = $iteminfo['lwin'] . $iteminfo['vintage'] . $iteminfo['bottleInCase'] . $iteminfo['bottleSize'];
									if(substr($item->sku, 0, -2) == 'LX'.$lwin18){
										
										$qty = (int) $item->quantity;
										#$qty = 100; #test to handle failed order PUSH
										
										$details = [
											'contractType' => $iteminfo['contractType'], #sib/sep/x
											#'orderType' => $iteminfo['orderType'],
											'orderType' => 'b', #bid
											'orderStatus' => $iteminfo['orderStatus'],
											'lwin' => $lwin18,
											'vintage' => $iteminfo['vintage'],
											'currency' => 'GBP',
											'price' => (int) $iteminfo['price'],
											'quantity' => $qty,
											'merchantRef' => $order->reference,
											'overrideFatFinger' => true, #Bypass system checks that prevent price keying errors.
										];
										
										
										#handle "special now" order...
										if($iteminfo['contractType'] == 'X'){
											$details['special'] = $iteminfo['special'];
											$details['specialOrderGUID'] = $iteminfo['orderGUID'];
										}
										
										#dd($item);
										$order_det = [];
										$order_det[] = $details;
										
										#we post each order line separately so we can correctly store GUID against the SKU
										
										$params = ['orders' => $order_det];
										#dd($params);
										#dd(json_encode($params));
										
										if($iteminfo['contractType'] == 'X'){
											#Log::debug('special order:');
											#Log::debug($params);
										}

										#$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params, 'debug' => true]);
										$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params]);
										$this->set_responsedata();

										#dd($this->responsedata);
										#Log::debug($this->responsedata);
										if($this->responsedata['status'] == 'OK'){
											foreach($this->responsedata['orders']['order'] as $order_data){
												if($order_data['orderGUID']){
													$order->additional('livex_guid_'.$order_data['orderGUID'], $item->sku);
													$item_posted = true;
												}
												
												if($order_data['errors']){
													#Log::warning(json_encode($this->responsedata));
													
													$err = new ErrorReport;
													$err->message = json_encode($this->responsedata);
													$err->code = $this->error_code;
													$err->line = __LINE__;
													$err->order_id = $order->id;
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
											$err->save();
										}
									}
								}
								
								if(!$item_posted){
									#Item not matched on LWIN, or not LX item - ignored and not posted to Liv-ex
									#Log::warning('unable to post order line '.$item->sku.' to Liv-ex');
									
									$err = new ErrorReport;
									$err->message = 'SKU '.$item->sku.' not posted to Liv-ex. Manual review required?';
									$err->code = $this->error_code;
									$err->line = __LINE__;
									$err->order_id = $order->id;
									$err->save();
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
							$err->save();
						}
					}
					elseif($offer_guids){
						#Log::warning('Unable to post order '.$order->id.' to Liv-ex');
						
						$err = new ErrorReport;
						$err->message = 'Unable to post order '.$order->id.' to Liv-ex. This may be issue with items failing pre-order checks, or an issue with the OrderStatusAPI call as there are qualifying items. Manual check required.';
						$err->code = $this->error_code;
						$err->line = __LINE__;
						$err->order_id = $order->id;
						$err->save();
					}
				}
				else{
					#Log::warning('Connection to Liv-ex failed');
					
					$err = new ErrorReport;
					$err->message = 'Unable to post order '.$order->id.' to Liv-ex. Connection to Liv-ex failed.';
					$err->code = $this->error_code;
					$err->line = __LINE__;
					$err->order_id = $order->id;
					$err->save();
				}
			}
			else{
				#dd('dont send to livex due to order total');
				
				$err = new ErrorReport;
				$err->message = 'Do not send to Liv-ex due to order total';
				$err->code = $this->error_code;
				$err->line = __LINE__;
				$err->order_id = $order->id;
				$err->save();
			}
		}
		else{
			#dd('dont send to livex due to cash payment method');
			
			$err = new ErrorReport;
			$err->message = 'Do not send to Liv-ex due to cash payment method';
			$err->code = $this->error_code;
			$err->line = __LINE__;
			$err->order_id = $order->id;
			$err->save();
		}
    }

    /**
     * Orders API – Delete order on Liv-ex if bid fails (after checkout payment)
     *
     * @param \Aero\Cart\Models\Order $order
     * @param string $livex_bid_guid
     * @return boolean
     */
    public function cancel(\Aero\Cart\Models\Order $order, $livex_bid_guid)
    {
		#check bid guid is assigned to order
		if($order->additional('livex_guid_'.$livex_bid_guid) != ''){
			$url = $this->base_url . 'exchange/v6/orders';
			
			$params = [
				'orderGUID' => [$livex_bid_guid],
			];

			#$this->response = $this->client->request('DELETE', $url, ['headers' => $this->headers, 'json' => $params, 'debug' => true]);
			$this->response = $this->client->request('DELETE', $url, ['headers' => $this->headers, 'json' => $params]);
			$this->set_responsedata();
			
			if($this->responsedata['status'] == 'OK'){
				$order->additional('livex_deleted_'.$livex_bid_guid, $order->additional('livex_guid_'.$livex_bid_guid));
				return true;
			}
			else{
				#Log::warning(json_encode($this->responsedata));
				
				$err = new ErrorReport;
				$err->message = json_encode($this->responsedata);
				$err->code = $this->error_code;
				$err->line = __LINE__;
				$err->order_id = $order->id;
				$err->save();
			}
		}
		else{
			$err = new ErrorReport;
			$err->message = $livex_bid_guid.' GUID not associated with order '.$order->id;
			$err->code = $this->error_code;
			$err->line = __LINE__;
			$err->order_id = $order->id;
			$err->save();
		}
		
		return false;
    }
}
