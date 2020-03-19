<?php

namespace Sypo\Livex\Providers;

use Sypo\Livex\Listeners\SendOrderToLivex;
use Aero\Cart\Events\OrderSuccessful;
use Aero\Admin\AdminModule;
use Aero\Common\Providers\ModuleServiceProvider;
use Aero\Common\Facades\Settings;
use Aero\Common\Settings\SettingGroup;
use Aero\Payment\Models\PaymentMethod;
use Spatie\Valuestore\Valuestore;
use Illuminate\Support\Facades\Log;

class ServiceProvider extends ModuleServiceProvider
{
    protected $listen = [
        OrderSuccessful::class => [
            SendOrderToLivex::class,
        ],
    ];

    protected $commands = [
        'Sypo\Livex\Console\Commands\Heartbeat',
        'Sypo\Livex\Console\Commands\SearchMarket',
    ];

    public function register(): void 
    {
        AdminModule::create('Livex')
            ->title('Liv-Ex')
            ->summary('Livex API integration settings for Aero Commerce')
            ->routes(__DIR__ .'/../../routes/admin.php')
            ->route('admin.modules.livex');
        
        $this->commands($this->commands);
    }
	
    public function boot(): void 
    {
        parent::boot();
		
        Settings::group('Livex', function (SettingGroup $group) {
            $group->boolean('enabled')->default(true);
            $group->integer('stock_threshold')->default(0);
            $group->integer('price_threshold')->default(250);
            $group->integer('margin_markup')->default(10);
            $group->integer('max_subtotal_in_basket')->default(3000);
        });
		
		$valuestore = Valuestore::make(storage_path('app/livex.json'));
		$valuestore->put('enabled', '1');
		$valuestore->put('stock_threshold', '0');
		$valuestore->put('price_threshold', '250');
		$valuestore->put('margin_markup', '10');
		$valuestore->put('max_subtotal_in_basket', '3000');
		
		$this->loadViewsFrom(__DIR__ . '/../../resources/views/', 'livex');
		
		PaymentMethod::setCartValidator(function ($method, $cart) {
			Log::debug('checking payment methods');
			#Log::debug($method->driver);
			#Log::debug(setting('Livex.max_subtotal_in_basket'));
			#Log::debug($cart->subtotal()->incValue);
			
			
			if($method->driver == 'cash'){
				/* if($cart->subtotal()->incValue > (setting('Livex.max_subtotal_in_basket') * 100)){
					return true;
				}
				return false; */
				
				#always allow bank transfer option
				return true;
			}
			elseif($method->driver == 'realex'){
				if($cart->subtotal()->incValue < (setting('Livex.max_subtotal_in_basket') * 100)){
					return true;
				}
				return false;
			}
			
			#default handling - shouldn't reach here
			return true;
			
			/* 
			#checking on Liv-Ex items only...
			$livex_subtotal = 0;
			foreach($cart->items() as $item){
				#dd($item);
				Log::debug($item->sku);
				if(substr($item->sku, 0, 2) == 'LX'){
					$livex_subtotal += $item->subtotal()->incValue;
				}
			}
			
			if($livex_subtotal < settings('max_subtotal_in_basket')){
				return true;
			}
			#dd($livex_subtotal);
			 */
		});
    }
}