<?php

namespace Sypo\Livex\Models;

use Sypo\Livex\Models\ErrorReport;
use Sypo\Livex\Models\OrderStatusAPI;
use Sypo\Livex\Models\MyPositionsAPI;
use Aero\Payment\Models\Payment;
use Aero\Store\Events\FormSubmitted;
use Aero\Cart\Models\Order;
use Aero\Cart\Models\OrderStatus;

class Refund
{
    protected $response;
    protected $error_code = 'refund_process';
    
	/**
     * Get Liv-ex Order GUIDs and line info from the order items
     *
     * @param \Aero\Cart\Models\Order $order
     * @return array
     */
    protected function get_eligable_items(\Aero\Cart\Models\Order $order){
		$dets = [];
		$items = $order->items()->where('sku', 'like', 'LX%')->get();
		$this->order_guids = [];
		if($items != null){
			foreach($items as $item){
				if($item->buyable()->first() === null or $item->buyable()->first()->product()->first() === null){
					#dd('item not found for order '.$order->id);
					$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
					return $dets;
				}
				$guid = $item->buyable()->first()->product()->first()->additional('livex_order_guid');
				if($guid){
					$dets[$guid] = ['sku' => $item->sku, 'qty' => $item->quantity];
				}
			}
		}
		
		#dd($dets);
		return $dets;
	}
    
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
				#$order->additional('refund_check', \Carbon\Carbon::now());
				
				#dd($order->additionals()->where('key', 'like', 'livex_guid_%')->get());
				$num_order_guids = $order->additionals()->where('key', 'like', 'livex_guid_%')->count();
				$num_trade_guids = $order->additionals()->where('key', 'like', 'livex_tradeid_%')->count();
				$num_suspended_guids = $order->additionals()->where('key', 'like', 'livex_suspended_%')->count();
				$num_deleted_guids = $order->additionals()->where('key', 'like', 'livex_deleted_%')->count();
				
				#dd("order guids {$num_order_guids} | trade guids {$num_trade_guids} | suspended guids {$num_suspended_guids} | deleted guids {$num_deleted_guids}");
				
				#items that are eligable for LX API (i.e. products with a guid)
				$dets = $this->get_eligable_items($order);
				if($order->items()->where('sku', 'like', 'LX%')->count() > 0){
					
					if($order->items()->where('sku', 'like', 'LX%')->count() == $num_trade_guids){
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
					elseif($num_order_guids > 0 and $num_trade_guids == 0 and $num_suspended_guids == 0 and $num_deleted_guids == 0){
						#order posted to order api but no PUSH notification receieved - check using mypositions api
						
						$pos = new MyPositionsAPI;
						$res = $pos->call($order);
						
						#dd($dets);
						dd('check mypositions api - '.$order->id);
					}
					elseif($num_suspended_guids > 0 or $num_deleted_guids > 0){
						#items suspended - check for refund...
						dd('check for refund - '.$order->id);
					}
					else{
						#dd('how did i get here? - '.$order->id);
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
