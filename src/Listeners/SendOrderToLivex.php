<?php

namespace Sypo\Livex\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Aero\Cart\Events\OrderSuccessful;
use Sypo\Livex\Models\OrderAPI;

class SendOrderToLivex implements ShouldQueue
{
    use Queueable;
	
    public function handle(OrderSuccessful $event)
    {
        $order = $event->order;
		#dd($order->subtotalPrice->incValue);
		#dd(setting('Livex.max_subtotal_in_basket'));
		Log::debug('in SendOrderToLivex Listener');
		
		#only send to Livex if order amount is less than threshold
		if($order->subtotalPrice->incValue < (setting('Livex.max_subtotal_in_basket') * 100)){
			#dd('send to livex');
			Log::debug('send to livex');
			
			#handle Liv-ex API call
			$livex = new OrderAPI;
			$livex->add($order);
		}
		else{
			
			#dd('dont send to livex due to order total');
			Log::debug('dont send to livex due to order total');
		}
    }
}