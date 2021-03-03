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
use Sypo\Delivery\Models\BondedWarehouseAddress;

class Refund
{
    protected $response;
    protected $error_code = 'refund_process';
    protected $orders_requiring_processing = [];
    
    /**
     * Handle order refund
     *
     * @return string
     */
    protected function handle_refund($amount, \Aero\Cart\Models\Order $order)
    {
        #dd($order->payment_methods->first());
        #dd($order->payments->filter->isSuccessful()->first());
        #dd($order->payment_methods->first()->getDriver());
		
		#$amount = 10;
		
		$payment = $order->payments->filter->isSuccessful()->first();
		#dd($payment->id);
		
		$driver = $order->payment_methods->first()->getDriver();
		$this->response = $driver->refund($amount, $payment);
		
		#send email to customer notifying refund, only if successful
		if($this->response->isSuccessful()){
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
		}
		
		#dd($this->response);
		return $this->response->isSuccessful();
    }
    
    /**
     * Check all successful orders and process auto-refund if issues with Liv-ex trade
     *
     * @return void
     */
    public function check_for_refund()
    {
        $successful_orders = OrderStatus::where('state', OrderStatus::SUCCESSFUL)->first()->orders()->get();
		#dd($successful_orders);
		
		$filtered = $successful_orders->filter(function ($order, $key) {
			return !$order->hasAdditional('refund_check');
		});

		$this->orders_requiring_processing = $filtered->all();
		#dd($this->orders_requiring_processing);
		
		if(count($this->orders_requiring_processing) > 0){
			$r = [];
			foreach($this->orders_requiring_processing as $order){
				#first resolve any PUSH notification issues
				$this->pre_process_order($order);
				$this->process_order($order);
				$r[] = $order->id;
			}
			#dd($r);
			#dd('refund process complete');
		}
		else{
			#dd('no new orders to process');
		}
    }
    
    /**
     * Get number to order to process 
     *
     * @return int
     */
    public function getCount()
    {
        return count($this->orders_requiring_processing);
    }
    
    /**
     * Resolve any PUSH notifications that didn't save to the order due to Order API latency
     *
     * @return void
     */
    protected function pre_process_order(\Aero\Cart\Models\Order $order)
    {
		
		#make sure we ignore the bonded warehouse charge product when checking order line count...
		$lx_line_count = $order->items()->where('sku', 'like', 'LX%')->count();
		$num_order_guids = $order->additionals()->where('key', 'like', 'livex_guid_%')->count();
		$num_trade_guids = $order->additionals()->where('key', 'like', 'livex_tradeid_%')->count();
		
		if($lx_line_count > 0){
			#handle Liv-ex order...
			if($lx_line_count == $num_trade_guids){
				#all items successfully traded - excellent
				dd('1');
			}
			elseif($lx_line_count != $num_order_guids){
				#some items failed to add to order API - manual review required
				dd('2');
			}
			else{
				#order posted to order api but we haven't receieved all PUSH notifications - check saved PUSH files
				#dd('3');
				#dd($order->reference);
				OrderPush::check_saved_push_logs($order);
			}
		}
		else{
			dd('4');
		}
    }
    
    /**
     * Check order to see if we require refunding
     *
     * @return void
     */
    protected function process_order(\Aero\Cart\Models\Order $order)
    {
		#make sure we ignore the bonded warehouse charge product when checking order line count...
		$bonded_warehouse_models = BondedWarehouseAddress::getModels();
		$line_count = $order->items()->whereNotIn('sku', $bonded_warehouse_models)->count();
		$lx_line_count = $order->items()->where('sku', 'like', 'LX%')->count();
		$num_order_guids = $order->additionals()->where('key', 'like', 'livex_guid_%')->count();
		$num_trade_guids = $order->additionals()->where('key', 'like', 'livex_tradeid_%')->count();
		$num_suspended_guids = $order->additionals()->where('key', 'like', 'livex_suspended_%')->count();
		$num_deleted_guids = $order->additionals()->where('key', 'like', 'livex_deleted_%')->count();
		$order_cancelled_on_livex = true;
		
		#dd("line count {$line_count} | LX line count {$lx_line_count} | order guids {$num_order_guids} | trade guids {$num_trade_guids} | suspended guids {$num_suspended_guids} | deleted guids {$num_deleted_guids}");
		
		if($lx_line_count > 0){
			#handle Liv-ex order...
			if($lx_line_count == $num_trade_guids){
				#dd('all traded successfully - '.$order->id);
				#all items successfully traded - excellent
				$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
				
				$err = new ErrorReport;
				$err->message = 'Skip refund check - all traded successfully';
				$err->code = $this->error_code;
				$err->line = __LINE__;
				$err->order_id = $order->id;
				$err->save();
				#dd('all traded successfully - '.$order->id);
			}
			elseif($lx_line_count != $num_order_guids){
				#some items failed to add to order API - manual review required
				$failed_items = $lx_line_count - $num_order_guids;
				#dd('Skip refund check - '.$failed_items.' LX item(s) failed to post to Liv-ex via order API - '.$order->id);
				
				$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
				
				$err = new ErrorReport;
				$err->message = 'Skip refund check - '.$failed_items.' LX item(s) failed to post to Liv-ex via order API. Manual review required.';
				$err->code = $this->error_code;
				$err->line = __LINE__;
				$err->order_id = $order->id;
				$err->save();
				#dd('Skip refund check - '.$failed_items.' LX item(s) failed to post to Liv-ex via order API - '.$order->id);
			}
			else{
				#order posted to order api but we haven't receieved all PUSH notifications - check using Order Status API
				
				#dd('check OrderStatusAPI - '.$order->id);
				
				$refund_r = [];
				$has_traded = 0;
				$order_guids = $order->additionals()->where('key', 'like', 'livex_guid_%')->get();
				foreach($order_guids as $k => $attribute){
					#dd($attribute);
					$guid = str_replace('livex_guid_', '', $attribute->key);
					$sku = $attribute->value;
					#dd($guid);
					
					$has_tradeid = $order->additionals()->where('key', 'like', 'livex_tradeid_%')->where('value', $guid)->first();
					$has_suspended_note = $order->additionals()->where('key', 'livex_suspended_'.$guid)->first();
					$has_deleted_note = $order->additionals()->where('key', 'livex_deleted_'.$guid)->first();
					
					$status = '';
					if(!$has_tradeid and !$has_suspended_note and !$has_deleted_note){
						#we don't know the final status of this bid - try checking Order Status API (will add suspended note to order if status=S)
						#dd('check order status of guid '.$guid.' from OrderStatusAPI');
						$s = new OrderStatusAPI;
						$status = $s->bid_status($order, $guid);
						#$status = 'S'; #TESTING!
						#dd($status);
					}
					else{
						#dd('ignore '.$guid.' from OrderStatusAPI calls - we already know status of this order bid');
						if($has_tradeid){
							$has_traded++;
						}
						elseif($has_suspended_note or $has_deleted_note){
							$refund_r[$sku] = $sku;
						}
					}
					
					if($status == 'T' or $has_tradeid){
						#item traded ok - great
						$has_traded++;
					}
					else{
						$refund_r[$sku] = $sku;
						
						#item not traded but we don't have a suspended/deleted notice - send cancel request to Liv-ex to make sure
						if(!$has_suspended_note and !$has_deleted_note){
							#delete the bid from Liv-ex
							$o = new OrderAPI;
							$order_cancelled_on_livex = $o->cancel($order, $guid);
						}
					}
				}
				
				#dd($refund_r);
				
				if($order_cancelled_on_livex){
					if($lx_line_count == $line_count and count($refund_r) == $line_count){
						#full refund, including shipping
						$amount = $order->total_rounded;
						#dd('full refund £'.$amount.' for order '.$order->id);
						$refunded = $this->handle_refund($amount, $order);
						if($refunded){
							#dd('refund success');
							$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
							
							$formatted_amount = (string) $order->total_price;
							$err = new ErrorReport;
							$err->message = 'Refunded full order amount '.$formatted_amount;
							$err->code = $this->error_code;
							$err->line = __LINE__;
							$err->order_id = $order->id;
							$err->save();
							#dd('refund success');
						}
						else{
							#if the refund fails, flag the order so we don't keep trying, just notify the order admin page and add error log
							#dd('refund failed');
							$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
							
							$err = new ErrorReport;
							$err->message = 'Manual review required. Refund failed';
							$err->code = $this->error_code;
							$err->line = __LINE__;
							$err->order_id = $order->id;
							$err->save();
						}
					}
					elseif($has_traded == $lx_line_count){
						#after checking OrderStatusAPI, all items traded successfully - update the order (no refund to process)
						#dd('Skip refund check - all traded successfully (after additional OrderStatusAPI check)');
						$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
						
						$err = new ErrorReport;
						$err->message = 'Skip refund check - all traded successfully (after additional OrderStatusAPI check)';
						$err->code = $this->error_code;
						$err->line = __LINE__;
						$err->order_id = $order->id;
						$err->save();
						#dd('Skip refund check - all traded successfully (after additional OrderStatusAPI check)');
					}
					else{
						#handle partial refund
						$total_to_refund = 0;
						$refundable_items = OrderItem::whereIn('sku', $refund_r)->get();
						foreach($refundable_items as $item){
							$total_to_refund += $item->total_rounded;
						}
						#dd('partial refund £'.$total_to_refund.' for order '.$order->id);
						$refunded = $this->handle_refund($total_to_refund, $order);
						
						if($refunded){
							#dd('refund success');
							$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
							
							$formatted_amount = $order->currency->format($total_to_refund / 100);
							$err = new ErrorReport;
							$err->message = 'Refunded partial order amount '.$formatted_amount;
							$err->code = $this->error_code;
							$err->line = __LINE__;
							$err->order_id = $order->id;
							$err->save();
						}
						else{
							#if the refund fails, flag the order so we don't keep trying, just notify the order admin page and add error log
							#dd('refund failed');
							$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
							
							$err = new ErrorReport;
							$err->message = 'Manual review required. Refund failed';
							$err->code = $this->error_code;
							$err->line = __LINE__;
							$err->order_id = $order->id;
							$err->save();
						}
					}
				}
				else{
					#order failed to cancel via orderapi
					#dd('refund failed');
					$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
					
					$err = new ErrorReport;
					$err->message = 'Manual review required. Order failed to cancel on Liv-ex';
					$err->code = $this->error_code;
					$err->line = __LINE__;
					$err->order_id = $order->id;
					$err->save();
				}
			}
		}
		else{
			#dd('ignore order (no LX items) - '.$order->id);
			#no items qualify for this order - set flag to ignore next time
			$order->additional('refund_check', \Carbon\Carbon::now()->format('d/m/Y H:i:s'));
			
			$err = new ErrorReport;
			$err->message = 'Skip refund check - no items qualify for this order';
			$err->code = $this->error_code;
			$err->line = __LINE__;
			$err->order_id = $order->id;
			$err->save();
			#dd('ignore order (no LX items) - '.$order->id);
		}
    }
}
