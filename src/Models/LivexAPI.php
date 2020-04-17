<?php

namespace Sypo\Livex\Models;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class LivexAPI
{
    /**
     * @var string
     */
    protected $language;
    protected $environment;
    protected $base_url;
    protected $headers;
    protected $client;
    protected $request;
    protected $response;
    protected $responsedata;
    protected $count;

    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->language = config('app.locale');
        $this->environment = env('LIVEX_ENV');
        $this->base_url = 'https://sandbox-api.liv-ex.com/';
        if($this->environment == 'live'){
			$this->base_url = 'https://api.liv-ex.com/';
		}
		
        $this->headers = [
			'CLIENT_KEY' => $this->get_client_key(),
			'CLIENT_SECRET' => $this->get_client_secret(),
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		
		$this->client = new Client();
    }

    /**
     * Get the client API key
     *
     * @return string
     */
    protected function get_client_key()
    {
        if($this->environment == 'live'){
			return env('LIVEX_CLIENT_KEY');
		}
        return env('LIVEX_CLIENT_KEY_SANDBOX');
    }

    /**
     * Get the client API secret
     *
     * @return string
     */
    protected function get_client_secret()
    {
        if($this->environment == 'live'){
			return env('LIVEX_CLIENT_SECRET');
		}
        return env('LIVEX_CLIENT_SECRET_SANDBOX');
    }
	
	public function get_status_code(){
		$status_code = $this->response->getStatusCode(); // 200
	}
	
	public function get_content_type(){
		$status_code = $this->response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
	}
	
	public function set_responsedata(){
		$this->responsedata = json_decode($this->response->getBody(), true);
	}
}
