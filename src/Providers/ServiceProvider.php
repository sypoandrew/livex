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
            ->title('Livex')
            ->summary('Livex API integration with Aero Commerce')
            #->permissions('Livex.custom') //optional
            ->routes(dirname(__DIR__).'/../routes/admin.php')
            ->route('admin.modules.livex');
    }
	
    public function boot(): void 
    {
        Settings::group('Livex', function (SettingGroup $group) {
            $group->boolean('enabled')->default(true);
            $group->float('still_wine_rate')->default(26.78);
            $group->float('sparkling_wine_rate')->default(34.30);
            $group->float('fortified_wine_rate')->default(35.70);
            $group->integer('litre_calc')->default(9);
        });
    }
}