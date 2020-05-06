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
		if($push->ping_test($request)){
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
		if($push->process_request($request)){
			return response()->json();
		}
		abort(403);
    }
}
