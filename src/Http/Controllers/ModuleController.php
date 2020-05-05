<?php

namespace Sypo\Livex\Http\Controllers;

use Illuminate\Http\Request;
use Aero\Admin\Facades\Admin;
use Aero\Admin\Http\Controllers\Controller;
use Sypo\Livex\Models\SearchMarketAPI;
use Sypo\Livex\Models\ErrorReport;
use Spatie\Valuestore\Valuestore;

class ModuleController extends Controller
{
    protected $data = []; // the information we send to the view

    /**
     * Show main settings form
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $livex_errors = ErrorReport::get();
        return view('livex::livex', ['livex_errors' => $livex_errors]);
    }
    
	/**
     * Update settings
     *
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
		if($request->isMethod('post')) {
			$validator = \Validator::make($request->all(), [
				'stock_threshold' => 'required|int',
				'lower_price_threshold' => 'required|int',
				'upper_price_threshold' => 'required|int',
				'lower_price_threshold_extra_margin_markup' => 'required|int',
				'margin_markup' => 'required|int',
				'max_subtotal_in_basket' => 'required|int',
			]);
			
			if($validator->fails()){
				return redirect()->back()->withErrors($validator->errors()->all());
			}
			
			$valuestore = Valuestore::make(storage_path('app/settings/Livex.json'));
			$valuestore->put('enabled', (int) $request->input('enabled'));
			$valuestore->put('stock_threshold', $request->input('stock_threshold'));
			$valuestore->put('lower_price_threshold', $request->input('lower_price_threshold'));
			$valuestore->put('upper_price_threshold', $request->input('upper_price_threshold'));
			$valuestore->put('lower_price_threshold_extra_margin_markup', $request->input('lower_price_threshold_extra_margin_markup'));
			$valuestore->put('margin_markup', $request->input('margin_markup'));
			$valuestore->put('max_subtotal_in_basket', $request->input('max_subtotal_in_basket'));
			
			
			return redirect()->back()->with('message', 'Settings updated');
		}
		else{
			abort(403);
		}
    }
    
	/**
     * Manually run the Search Market API
     *
     * @return void
     */
    public function search_market(Request $request)
    {
    	$l = new SearchMarketAPI;
		$l->process_all();
		
		return redirect()->back()->with('message', 'Successfully run the Search Market API');
    }
}
