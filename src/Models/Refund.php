<?php

namespace Sypo\Livex\Models;

use Sypo\Livex\Models\ErrorReport;
use Sypo\Livex\Models\OrderAPI;
use Sypo\Livex\Models\OrderStatusAPI;
use Sypo\Livex\Models\MyPositionsAPI;
use Aero\Payment\Models\Payment;
use Aero\Store\Events\FormSubmitted;
use Aero\Cart\Models\Order;
use Aero\Cart\Models\OrderItem;
use Aero\Cart\Models\OrderStatus;

class Refund
{
    protected $response;
    protected $error_code = 'refund_process';
    
    /**
     * Handle order refund
     *
     * @return string
     */
    public function handle_refund($amount, \Aero\Cart\Models\Order $order)
    {
        #dd($order->payment_methods->first());
        #dd($order->payments->filter->isSuccessful()->first());
        #dd($order->payment_methods->first()->getDriver());
		
		$amount = 10;
		
		$payment = $order->payments->filter->isSuccessful()->first();
		#dd($payment->id);
		
		$driver = $order->payment_methods->first()->getDriver();
		$this->response = $driver->refund($amount, $payment);
		
		
		$items = $order->items()->get();
		
		$order_r = $order->toArray();
		$order_r['items'] = $items->toArray();
		foreach($items as $k => $item){
			$order_r['items'][$k]['tags'] = $item->buyable()->first()->product()->first()->tags()->get()->toArray();
		}
		
		$params = [
		'email' => $order->email, #field to hook into 'send to customer'
		'refundtype' => ($order->isPaymentsFullyRefunded()) ? 'full' : 'partial',
		'order' => $order_r,
		];
		
		#dd($params);
		#send customer email notification
		event(new FormSubmitted('refund', $params));
		
		#dd($this->response);
		return $this->response->successful;
    }
    
    /**
     * Handle order refund
     *
     * @return string
     */
    public function check_for_refund()
    {
        $successful_orders = OrderStatus::where('state', OrderStatus::SUCCESSFUL)->first()->orders()->get();
		#dd($successful_orders);
		
		$filtered = $successful_orders->filter(function ($order, $key) {
			return !$order->hasAdditional('refund_check');
		});

		$orders_requiring_processing = $filtered->all();
		#dd($orders_requiring_processing);
		
		$r = [];
		foreach($orders_requiring_processing as $order){
			#if($order->id == 85){
				$line_count = $order->items()->count();
				$lx_line_count = $order->items()->where('sku', 'like', 'LX%')->count();
				$num_order_guids = $order->additionals()->where('key', 'like', 'livex_guid_%')->count();
				$num_trade_guids = $order->additionals()->where('key', 'like', 'livex_tradeid_%')->count();
				$num_suspended_guids = $order->additionals()->where('key', 'like', 'livex_suspended_%')->count();
				$num_deleted_guids = $order->additionals()->where('key', 'like', 'livex_deleted_%')->count();
				
				#dd("line count {$line_count} | LX line count {$lx_line_count} | order guids {$num_order_guids} | trade guids {$num_trade_guids} | suspended guids {$num_suspended_guids} | deleted guids {$num_deleted_guids}");
				
				if($lx_line_count > 0){
					#handle Liv-ex order...
					if($lx_line_count == $num_trade_guids){
						#all items successfully traded - excellent
						$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
						
						$err = new ErrorReport;
						$err->message = 'Skip refund check - all traded successfully';
						$err->code = $this->error_code;
						$err->line = __LINE__;
						$err->order_id = $order->id;
						$err->save();
						dd('all traded successfully - '.$order->id);
					}
					elseif($lx_line_count != $num_order_guids){
						#some items failed to add to order API - manual review required
						$failed_items = $lx_line_count - $num_order_guids;
						dd('Skip refund check - '.$failed_items.' LX item(s) failed to post to Liv-ex via order API - '.$order->id);
						
						$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
						
						$err = new ErrorReport;
						$err->message = 'Skip refund check - '.$failed_items.' LX item(s) failed to post to Liv-ex via order API. Manual review required.';
						$err->code = $this->error_code;
						$err->line = __LINE__;
						$err->order_id = $order->id;
						$err->save();
					}
					else{
						#order posted to order api but we haven't receieved all PUSH notifications - check using Order Status API
						
						dd('check OrderStatusAPI - '.$order->id);
						
						$refund_r = [];
						$has_traded = 0;
						$order_guids = $order->additionals()->where('key', 'like', 'livex_guid_%')->get();
						foreach($order_guids as $k => $sku){
							$guid = str_replace('livex_guid_', '', $k);
							
							$has_tradeid = $order->additionals()->where('key', 'like', 'livex_tradeid_%')->where('value', $guid)->first();
							$has_suspended_note = $order->additionals()->where('key', 'livex_suspended_'.$guid)->first();
							$has_deleted_note = $order->additionals()->where('key', 'livex_deleted_'.$guid)->first();
							
							$status = '';
							if(!$has_tradeid and !$has_suspended_note and !$has_deleted_note){
								#we don't know the final status of this bid - try checking Order Status API (will add suspended note to order if status=S)
								$s = new OrderStatusAPI;
								$status = $s->bid_status($order, $guid);
								dd($status);
							}
							else{
								dd('ignore '.$guid.' - we already know status of this order bid');
							}
							
							if($status == 'T' or $has_tradeid){
								#item traded ok - great
								$has_traded++;
							}
							else{
								$refund_r[] = $sku;
								
								#item not traded but we don't have a suspended/deleted notice - send cancel request to Liv-ex to make sure
								if(!$has_suspended_note and !$has_deleted_note){
									#delete the bid from Liv-ex
									$o = new OrderAPI;
									$o->cancel($order, $guid);
								}
							}
						}
						
						dd($refund_r);
						
						if(count($refund_r) == $line_count){
							#full refund
							$amount = $order->total_rounded;
							dd('full refund £'.$amount.' for order '.$order->id);
							#$this->handle_refund($amount, $order);
						}
						else{
							#partial refund
							$total_to_refund = 0;
							$refundable_items = OrderItem::whereIn('sku', $refund_r)->get();
							foreach($refundable_items as $item){
								$total_to_refund += $item->total_rounded;
							}
							dd('partial refund £'.$total_to_refund.' for order '.$order->id);
							#$this->handle_refund($total_to_refund, $order);
						}
					}
				}
				else{
					#no items qualify for this order - set flag to ignore next time
					$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
					
					$err = new ErrorReport;
					$err->message = 'Skip refund check - no items qualify for this order';
					$err->code = $this->error_code;
					$err->line = __LINE__;
					$err->order_id = $order->id;
					$err->save();
					dd('ignore order (no LX items) - '.$order->id);
				}
			#}
			
			$r[] = $order->id;
		}
		dd($r);
    }
}
