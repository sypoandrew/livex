<?php

namespace Sypo\Livex\Models;

use Aero\Catalog\Models\TagGroup;
use Aero\Common\Models\AdditionalAttribute;
use Illuminate\Support\Facades\Log;

class Helper
{
    /**
     * Get all required tag groups for importing tag data
     *
     * @return Aero\Catalog\Models\TagGroup
     */
    public static function get_tag_groups()
    {
		$language = config('app.locale');
		$groups = TagGroup::get();
		$tag_groups = [];
		foreach($groups as $g){
			$tag_groups[$g->name] = $g;
		}
		return $tag_groups;
    }

    /**
     * Calculate total value of items in basket of Livex items to prevent CC option in checkout
     *
     * @param \Aero\Cart\Cart $group
     * @return boolean
     */
    public function basket_items_limit_reached(\Aero\Cart\Cart $cart)
    {
		#dd($cart->items());
		$items = $cart->items();
		$livex_total = 0;
		if(!$items->isEmpty()){
			foreach($items as $item){
				if(substr($item->sku, 0, 2) == 'LX'){
					$livex_total += $item->subtotal();
				}
			}
		}
		
		if($livex_total > (setting('Livex.max_subtotal_in_basket') * 100)){
			return true;
		}
		return false;
    }

    /**
     * Get all Liv-ex GUIDs for order
     *
     * @param \Aero\Cart\Models\Order $order
     * @return \Aero\Common\Models\AdditionalAttribute
     */
    public static function get_order_guids(\Aero\Cart\Models\Order $order)
    {
		#Log::debug(AdditionalAttribute::where('attributable_type', 'order')->where('attributable_id', $order->id)->where('key', 'LIKE', 'livex_guid%')->toSql());
		return AdditionalAttribute::where('attributable_type', 'order')->where('attributable_id', $order->id)->where('key', 'LIKE', 'livex_guid%')->get();
    }
}
