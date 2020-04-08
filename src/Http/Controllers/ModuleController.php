<?php

namespace Sypo\Livex\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Aero\Admin\Facades\Admin;
use Aero\Admin\Http\Controllers\Controller;
use Sypo\Livex\Models\SearchMarketAPI;

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
        return view('livex::livex', $this->data);
    }
    
	/**
     * Update settings
     *
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
		$imageName = time().'.'.$request->file->getClientOriginalExtension();
		$imageName = $request->file->getClientOriginalExtension();
        $request->file->move(storage_path('app/image_library'), $imageName);
         
    	return response()->json(['success'=>'You have successfully upload file.']);
		
		
		/* $res = ['success'=>false,'data'=>false,'error'=>[]];
		
        $validator = \Validator::make($request->all(), [
            'stock_threshold' => 'required|int',
            'price_threshold' => 'required|int',
            'margin_markup' => 'required|int',
        ]);
		
		if($validator->fails()){
			$res['error'] = $validator->errors()->all();
			return response()->json($res);
		}
		
		$formdata = $request->json()->all();
		Log::debug($formdata);
		
		
        return redirect()->back()->with('message', 'Settings updated.'); */
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
		
		return redirect()->back()->with('message', 'Successfully run the Search Market API.');
    }
    
	/**
     * Manually run the Placeholder image
     *
     * @return void
     */
    public function placeholder_image(Request $request)
    {
    	\Artisan::call('sypo:livex:image');
		
		return redirect()->back()->with('message', 'Successfully run the Placeholder image routine.');
    }
}
