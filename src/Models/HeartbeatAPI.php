<?php
namespace Sypo\Livex\Models;

use Illuminate\Support\Facades\Log;
use Sypo\Livex\Models\LivexAPI;

class HeartbeatAPI extends LivexAPI
{
    /**
     * Heartbeat API – Checks that Liv-ex server is up and available
     *
     * @return boolean
     */
    public function call()
    {
        $url = $this->base_url . 'exchange/heartbeat';
        
		try {
			$this->response = $this->client->get($url, ['headers' => $this->headers]);
			$this->set_responsedata();
			
			if($this->responsedata['status'] == 'OK'){
				return true;
			}
			else{
				Log::warning(json_encode($this->responsedata));
			}
		}
		catch(RequestException $e) {
			Log::warning($e);
		}
		
		return false;
    }
}
