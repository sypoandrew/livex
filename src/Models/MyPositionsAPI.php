<?php

namespace Sypo\Livex\Models;

use Sypo\Livex\Models\LivexAPI;
use Aero\Common\Models\Currency;

class MyPositionsAPI extends LivexAPI
{
    protected $error_code = 'my_positions_api';
    protected $currency;


    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
		
		$this->currency = Currency::where('code', 'GBP')->first();
	}

    /**
     * Order Status API – check status of offers in basket (prior to checkout payment)
     *
     * @param \Aero\Cart\Models\Order $order
     * @return void
     */
    public function call(\Aero\Cart\Models\Order $order)
    {
		$proceed_with_order = true;
		
        $url = $this->base_url . 'exchange/v1/myPositions';
		
		$order_guid = '';
		
		$params = [
			'currency' => $this->currency->code,
			'orderGUID' => $order_guid,
		];

		#$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params, 'debug' => true]);
		$this->response = $this->client->post($url, ['headers' => $this->headers, 'json' => $params]);
		$this->set_responsedata();

		#dd($this->responsedata);
		if($this->responsedata['status'] == 'OK'){
			foreach($this->responsedata['positions'] as $position){
				//handle response here...
				dd($position);
			}
		}
		else{
			$err = new ErrorReport;
			$err->message = json_encode($this->responsedata);
			$err->code = $this->error_code;
			$err->line = __LINE__;
			$err->order_id = $order->id;
			$err->save();
		}
    }
}
