<?php

namespace Sypo\Livex\Models;

use Illuminate\Database\Eloquent\Model;

class Livex extends Model
{
    protected $base_url = 'https://api.liv-ex.com/';
    private $client_key = config('LIVEX_CLIENT_KEY');
    private $client_secret = config('LIVEX_CLIENT_SECRET');

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Heartbeat API – Check that Liv-ex is up and available
     *
     * @return void
     */
    public function heartbeat()
    {
        $url = $this->base_url . 'exchange/heartbeat';
        $headers = [
			'CLIENT_KEY' => $this->client_key,
			'CLIENT_SECRET' => $this->client_secret,
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		
        $client = new \GuzzleHttp\Client();
		$response = $client->request('GET', $url);

		$status_code = $response->getStatusCode(); // 200
		$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
		$res = $response->getBody();
    }

    /**
     * Active Market API – Receive active bid and offer information for any wine in the Liv-ex database
     *
     * @return void
     */
    public function active_market()
    {
        $url = $this->base_url . 'exchange/v4/activeMarket';
        $headers = [
			'CLIENT_KEY' => $this->client_key,
			'CLIENT_SECRET' => $this->client_secret,
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		
		$params = [
			'lwin' => 'LWIN18', #LWIN11/LWIN16/LWIN18
			'priceType' => 'all', #all/bid/offer/list
			'contractType' => 'all', #all/SIB/SEP/X
			'bidFullDepth' => false, #true/false
			'offerFullDepth' => false, #true/false
			'listFullDepth' => false, #true/false
			'timeframe' => 'current', #current/15/30/45/90
			'currency' => 'gbp',
			'forexType' => 'spread', #spread/spot
		];
		
		
        $client = new \GuzzleHttp\Client($headers);
		$response = $client->request('GET', $url, $params);

		$status_code = $response->getStatusCode(); // 200
		$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
		$res = $response->getBody();
    }

    /**
     * Search Market API – Receive live Bids and Offers based on defined filters
     *
     * @return void
     */
    public function search_market()
    {
        $url = $this->base_url . 'search/v1/searchMarket';
        $headers = [
			'CLIENT_KEY' => $this->client_key,
			'CLIENT_SECRET' => $this->client_secret,
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		
		$params = [
			'lwin' => 'LWIN18', #LWIN11/LWIN16/LWIN18
			'currency' => 'gbp',
			'minPrice' => 0,
			'maxPrice' => 0,
		];
		
		
        $client = new \GuzzleHttp\Client($headers);
		$response = $client->request('GET', $url, $params);

		$status_code = $response->getStatusCode(); // 200
		$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
		$res = $response->getBody();
    }
}
