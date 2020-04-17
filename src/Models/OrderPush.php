<?php

namespace Sypo\Livex\Models;

use Illuminate\Support\Facades\Log;
use Aero\Cart\Models\Order;
use Aero\Common\Models\AdditionalAttribute;

class OrderPush
{
    protected $approved_user_agents = [];
	
	/**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct()
    {
		#Liv-ex user agent
		$this->approved_user_agents[] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X x.y; rv:42.0) Gecko/20100101 Firefox/42.0';
		#SYPO test Chrome
		$this->approved_user_agents[] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36';
	}
	
	/**
     * Get approved user agents for authorising notificiations
     *
     * @return array
     */
    public function get_user_agents()
    {
		return $this->approved_user_agents;
	}
	
    /**
     * Check the PUSH request headers are valid
     *
     * @param \Illuminate\Http\Request $request
     * @return boolean
     */
    public function valid_headers(\Illuminate\Http\Request $request)
    {
		if($request->headers->has('user-agent') and in_array($request->headers->get('user-agent'), $this->get_user_agents())){
			return true;
		}
		return false;
    }
}
