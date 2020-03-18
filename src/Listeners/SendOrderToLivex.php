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
        // API calls to Livex
		$livex = new Livex;
		$livexId = $livex->add_order($order);
		
        $order->additional('livex_guid', $livexId);
    }
}