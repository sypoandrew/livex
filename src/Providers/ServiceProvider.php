<?php

namespace Sypo\Livex\Providers;

use Sypo\Livex\Listeners\SendOrderToLivex;
use Aero\Cart\Events\OrderSuccessful;
use Aero\Admin\AdminModule;
use Aero\Common\Providers\ModuleServiceProvider;
use Aero\Common\Facades\Settings;
use Aero\Common\Settings\SettingGroup;
use Aero\Payment\Models\PaymentMethod;
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
        'Sypo\Livex\Console\Commands\PlaceholderImage',
        'Sypo\Livex\Console\Commands\UpdateDefaultImage',
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
            $group->string('image_report_send_to_email')->default('andrew@sypo.uk');
            $group->string('image_report_send_from_email')->default('sales@vinquinn.com');
            $group->string('image_report_send_from_name')->default('VinQuinn Sales');
        });
		
		$this->loadRoutesFrom(__DIR__ . '/../../routes/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
		$this->loadViewsFrom(__DIR__ . '/../../resources/views/', 'livex');
    }
}