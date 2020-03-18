<?php

namespace Sypo\Livex\Listeners;

use Aero\Cart\Events\OrderSuccessful;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Sypo\Livex\Models\Livex;

class SendOrderToLivex implements ShouldQueue
{
    use Queueable;
	
    public function handle(OrderSuccessful $event)
    {
        $order = $event->order;
        #only send to Livex if order amount is less than threshold
		if($order->subtotal() < setting('Livex.max_subtotal_in_basket')){
			#handle Liv-ex API call
			$livex = new Livex;
			$livexId = $livex->add_order($order);
			
			$order->additional('livex_guid', $livexId);
		}
    }
}