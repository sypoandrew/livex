<?php
namespace Sypo\Livex\Models;

use Illuminate\Support\Facades\Log;
use Aero\Catalog\Models\Product;
use Aero\Catalog\Models\Variant;

class LivexFeed
{
    protected $filename = 'livexfeed.xml';
    protected $lang;
    protected $name_limit = 160;
    protected $result;
    
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->lang = config('app.locale');
    }
    
    /**
     * Generate the Liv-Ex XML feed
     *
     * @return boolean
     */
    public function call()
    {
		$this->result = ['success' => false, 'processed' => 0, 'errors' => []];
        try {
			$arr = [];
			#get all active non-LX products
			$products = Product::where('model', 'not like', 'LX%')->visible()->published()->hasStock()->hasVisibleVariants()->get();
            
			foreach($products as $p){
				$variant = false;
				
				$variants = $p->visibleVariants();
				foreach($variants as $v){
					$dutystatus = optional($v->attributeGroups()->select('attributes.name')->where("attribute_groups.name->{$this->lang}", "Duty Status")->first())->name;
					if($dutystatus == 'En Primeur' or $dutystatus == 'Bond'){
						$variant = $v;
						break;
					}
				}
				#if no IB item we take the duty paid as last resort
				if(!$variant){
					foreach($variants as $v){
						$dutystatus = optional($v->attributeGroups()->select('attributes.name')->where("attribute_groups.name->{$this->lang}", "Duty Status")->first())->name;
						if($dutystatus == 'Duty Paid'){
							$variant = $v;
							break;
						}
					}
				}
				unset($variants);
				
				if($variant){
					$dstatus = 'IB';
					if($dutystatus == 'Duty Paid'){
						$dstatus = 'DP';
					}
					
					$price_formatted = '0.00';
					if($price = $variant->prices()->where('quantity', 1)->first()){
						$price_formatted = number_format(($price->value_inc / 100), 2, '.', '');
						#4/9/20 - WS settings already adding VAT for DP wines, handle price as exVAT in feed
						if($dutystatus == 'Duty Paid'){
							$price_formatted = number_format(($price->value_ex / 100), 2, '.', '');
						}
					}
					$bottle_size = $this->getTag($p, 'Bottle Size');
					$case_size = $this->getTag($p, 'Case Size');
					
					if($case_size){
						$bottle = "{$case_size} x {$bottle_size}";
					}
					else{
						$bottle = $bottle_size;
					}
					
					$country = $this->getTag($p, 'Country');
					$region = $this->getTag($p, 'Region');
					$subregion = $this->getTag($p, 'Sub Region');
					$colour = $this->getTag($p, 'Colour');
					$winetype = $this->getTag($p, 'Wine Type');
					
					if($country){
						$country = ', ' . $country;
					}
					if($subregion){
						$subregion = ', ' . $subregion;
					}
					if($region){
						$region = ', ' . $region;
					}
					if($colour){
						$colour = ', ' . $colour;
					}
					if($winetype){
						$winetype = ', ' . $winetype;
					}
					
					#13/8/20 - ignore winetype
					$winetype = '';
					
					$name = $p->name . $country . $region . $subregion . $colour . $winetype;
					if(strlen($name) > $this->name_limit){
						//remove subregion
						$name = $p->name . $country . $region . $colour . $winetype;
					}
					if(strlen($name) > $this->name_limit){
						//remove region
						$name = $p->name . $country . $colour . $winetype;
					}
					if(strlen($name) > $this->name_limit){
						//remove wine type
						$name = $p->name . $country . $colour;
					}
					if(strlen($name) > $this->name_limit){
						//sod it - just concat
						$name = substr($name, 0, $this->name_limit);
					}
					
					
					$arr[] = [
					'name' => $name,
					'sku' => $p->model,
					'qty' => $variant->stock_level,
					'tax' => $dstatus,
					'price' => $price_formatted,
					'vintage' => $this->getTag($p, 'Vintage'),
					'case_size' => $case_size,
					'bottle_size' => $bottle,
					'url' => $p->getUrl(true),
					];
				}
			}
			
			$xmlString = '';
			$xmlString .= '<?xml version="1.0" encoding="UTF-8"?><livex-datafeed><wine-list>';
			foreach($arr as $wine){
				$xmlString .= '<wine><wine-name><![CDATA['.$wine['name'].']]></wine-name><sku>'.$wine['sku'].'</sku><qty>'.$wine['qty'].'</qty><price>'.$wine['price'].'</price><vintage>'.$wine['vintage'].'</vintage><case-quantity>'.$wine['case_size'].'</case-quantity><bottle-size>'.$wine['bottle_size'].'</bottle-size><link>'.$wine['url'].'</link></wine>';
			}
			$xmlString .= '</wine-list></livex-datafeed>';
			
			$dom = new \DOMDocument;
			$dom->preserveWhiteSpace = false;
			$dom->loadXML($xmlString);
			//Save XML as a file
			$dom->save(public_path($this->filename));
			
			$this->result['success'] = true;
			$this->result['processed'] = count($arr);
		}
		catch(Exception $e) {
			Log::warning($e);
			$this->result['errors'][] = $e;
		}
		
		return $this->result;
    }

    /**
     * Get the tag value, if available
     * 
     * @param Aero\Catalog\Models\Product $product
     * @param string $tag_group_name
     * 
     * @return null|string
     */
    protected function getTag(\Aero\Catalog\Models\Product $product, $tag_group_name)
    {
        return optional($product->tags()->join('tag_groups', 'tag_groups.id', '=', 'tags.tag_group_id')->where("tag_groups.name->{$this->lang}", $tag_group_name)->first())->name;
    }
}
