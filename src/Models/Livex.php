<?php

namespace Sypo\Livex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Livex extends Model
{
    protected $base_url = 'https://api.liv-ex.com/';

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
			'CLIENT_KEY' => config('LIVEX_CLIENT_KEY'),
			'CLIENT_SECRET' => config('LIVEX_CLIENT_SECRET'),
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		
		try {
			#$client = new Client();
			#$client->setDefaultOption('headers', $headers);
			#$response = $client->request('GET', $url);
			
			$client = new Client();
			#$client->setDefaultOption('headers', $headers);
			$response = $client->get($url, ['headers' => $headers]);
			
			/* $client = new Client($url, [
				'base_url' => $url,
				'defaults' => [
					'headers' => ['Foo' => 'Bar'],
					'query'   => ['testing' => '123'],
					'auth'    => ['username', 'password'],
					'proxy'   => 'tcp://localhost:80'
				]
			]);
			$response = $client->send(); */
			
			

			$status_code = $response->getStatusCode(); // 200
			$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
			$res = $response->getBody();
			
			Log::debug('heartbeat 1');
			Log::debug($status_code);
			Log::debug($content_type);
			Log::debug($res);
		}
		catch(RequestException $e) {
			Log::debug($e);
		}
		
		try {
			#$client = new Client();
			#$client->setDefaultOption('headers', $headers);
			#$response = $client->request('GET', $url);
			
			$client = new Client();
			#$client->setDefaultOption('headers', $headers);
			$response = $client->get($url, ['auth' =>  [config('LIVEX_CLIENT_KEY'), config('LIVEX_CLIENT_SECRET')], 'headers' => $headers]);
			
			/* $client = new Client($url, [
				'base_url' => $url,
				'defaults' => [
					'headers' => ['Foo' => 'Bar'],
					'query'   => ['testing' => '123'],
					'auth'    => ['username', 'password'],
					'proxy'   => 'tcp://localhost:80'
				]
			]);
			$response = $client->send(); */
			
			

			$status_code = $response->getStatusCode(); // 200
			$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
			$res = $response->getBody();
			
			Log::debug('heartbeat 2');
			Log::debug($status_code);
			Log::debug($content_type);
			Log::debug($res);
		}
		catch(RequestException $e) {
			Log::debug($e);
		}
		
		try {
			#$client = new Client();
			#$client->setDefaultOption('headers', $headers);
			#$response = $client->request('GET', $url);
			
			$client = new Client();
			#$client->setDefaultOption('headers', $headers);
			$response = $client->get($url, ['auth' =>  [config('LIVEX_CLIENT_KEY'), config('LIVEX_CLIENT_SECRET')]]);
			
			/* $client = new Client($url, [
				'base_url' => $url,
				'defaults' => [
					'headers' => ['Foo' => 'Bar'],
					'query'   => ['testing' => '123'],
					'auth'    => ['username', 'password'],
					'proxy'   => 'tcp://localhost:80'
				]
			]);
			$response = $client->send(); */
			
			

			$status_code = $response->getStatusCode(); // 200
			$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
			$res = $response->getBody();
			
			Log::debug('heartbeat 3');
			Log::debug($status_code);
			Log::debug($content_type);
			Log::debug($res);
		}
		catch(RequestException $e) {
			Log::debug($e);
		}
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
			'CLIENT_KEY' => config('LIVEX_CLIENT_KEY'),
			'CLIENT_SECRET' => config('LIVEX_CLIENT_SECRET'),
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
		
		
        $client = new \GuzzleHttp\Client();
		$client->setDefaultOption('headers', $headers);
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
			'CLIENT_KEY' => config('LIVEX_CLIENT_KEY'),
			'CLIENT_SECRET' => config('LIVEX_CLIENT_SECRET'),
			'ACCEPT' => 'application/json',
			'CONTENT-TYPE' => 'application/json',
		];
		
		$params = [
			'lwin' => 'LWIN18', #LWIN11/LWIN16/LWIN18
			'currency' => 'gbp',
			'minPrice' => 0,
			'maxPrice' => 0,
		];
		
		
        $client = new \GuzzleHttp\Client();
		$client->setDefaultOption('headers', $headers);
		$response = $client->request('GET', $url, $params);

		$status_code = $response->getStatusCode(); // 200
		$content_type = $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
		$res = $response->getBody();
    }
}
