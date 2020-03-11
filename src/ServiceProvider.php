<?php

namespace Sypo\Livex;

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
            ->routes(__DIR__ .'/../routes/admin.php')
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
		
		#$this->loadViewsFrom(__DIR__ . '/../resources/views/modules/livex/', 'livex');
    }
}