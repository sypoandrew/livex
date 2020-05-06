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
        #dd('in SendOrderToLivex Listener');
        
        #handle Liv-ex API call
        $livex = new OrderAPI;
        $livex->add($order);
    }
}