<?php

namespace Sypo\Livex\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Aero\Cart\Models\Order;
use Sypo\Livex\Models\OrderPush;
use Sypo\Livex\Models\Helper;

class OrderPushController extends Controller
{
    /**
     * Handle a Liv-ex HEAD request ping test
     *
     * @return \Illuminate\Http\Response
     */
    public function ping(Request $request)
    {
        $push = new OrderPush;
		if($push->valid_headers($request)){
			return response()->json();
		}
		abort(403);
    }
    
    /**
     * Handle a Liv-ex POST request with trade confirmation
     *
     * @return \Illuminate\Http\Response
     */
    public function post(Request $request)
    {
        $push = new OrderPush;
		if($push->valid_headers($request)){
			$data = $request->json()->all();
			#dd($data);
			if(isset($data['trade'])){
				$order = null;
				if(isset($tradedata['trade']['merchant_ref'])){
					$order = Order::where('reference', $tradedata['trade']['merchant_ref'])->first();
					if($order !== null){
						if(isset($tradedata['trade']['order_guid'])){
							$livex_order_guid = Helper::find_order_guid($order, $tradedata['trade']['order_guid']);
							
							if($livex_order_guid !== null){
								#matched the GUID - let's save the trade id
								
								$key = str_replace('livex_guid', 'livex_tradeid', $livex_order_guid->key);
								$order->additional($key, $tradedata['trade']['trade_id']);
							}
							else{
								#order guid not found
								Log::warning('Liv-ex Order PUSH order GUID '.$tradedata['trade']['order_guid'].' not matched against order');
							}
						}
						else{
							#order not found...
							Log::warning('Liv-ex Order PUSH no order GUID found');
						}
					}
					else{
						#order not found...
						Log::warning('Liv-ex Order PUSH order not found ' . $tradedata['trade']['merchant_ref']);
					}
				}
				else{
					#something's wrong - we shouldn't reach here
					Log::warning('Liv-ex Order PUSH no order reference found');
				}
				
				return response()->json();
			}
			else{
				#something's wrong - we shouldn't reach here
				Log::warning('Liv-ex Order PUSH order invalid request');
			}
		}
		abort(403);
    }
}
