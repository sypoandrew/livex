<?php

namespace Sypo\Livex\Models;

use Aero\Catalog\Models\TagGroup;

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
		$groups = TagGroup::whereIn("name->{$language}", ['Bottle Size', 'Case Size', 'Colour', 'Country', 'Region', 'Sub Region', 'Vintage', 'Wine Type', 'Burgundy Cru', 'Liv-Ex Order GUID'])->get();
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
}
