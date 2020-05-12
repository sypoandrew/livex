<?php

namespace Sypo\Livex\Models;

use Sypo\Livex\Models\ErrorReport;
use Aero\Payment\Models\Payment;
use Aero\Store\Events\FormSubmitted;

class Refund
{
    var $response;
    
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
}
