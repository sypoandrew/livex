<?php

namespace Sypo\Livex\Providers;

use Aero\Admin\AdminModule;
use Aero\Common\Providers\ModuleServiceProvider;
use Aero\Common\Facades\Settings;
use Aero\Common\Settings\SettingGroup;

class ServiceProvider extends ModuleServiceProvider
{
    public function register(): void 
    {
        AdminModule::create('Livex')
            ->title('Liv-Ex')
            ->summary('Livex API integration settings for Aero Commerce')
            ->routes(__DIR__ .'/../../routes/admin.php')
            ->route('admin.modules.livex');
    }
	
    public function boot(): void 
    {
        Settings::group('Livex', function (SettingGroup $group) {
            $group->boolean('enabled')->default(true);
            $group->integer('stock_threshold')->default(0);
            $group->integer('price_threshold')->default(500);
            $group->integer('margin_markup')->default(10);
        });
		
		$valuestore = Valuestore::make(storage_path('app/livex.json'));
		$valuestore->put('enabled', '1');
		$valuestore->put('stock_threshold', '0');
		$valuestore->put('price_threshold', '500');
		$valuestore->put('margin_markup', '10');
		
		$this->loadViewsFrom(__DIR__ . '/../../resources/views/', 'livex');
    }
}