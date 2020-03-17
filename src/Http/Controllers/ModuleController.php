<?php

namespace Sypo\Livex\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Aero\Admin\Facades\Admin;
use Aero\Admin\Http\Controllers\Controller;
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
        $l = new \Sypo\Livex\Models\Livex;
		$l->heartbeat();
		$l->search_market();
		
        return view('livex::livex', $this->data);
    }
    
	/**
     * Update settings
     *
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
		$res = ['success'=>false,'data'=>false,'error'=>[]];
		
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
		
		
		/* $valuestore = Valuestore::make(storage_path('app/livex.json'));
		$valuestore->put('enabled', $formdata['enabled']);
		$valuestore->put('stock_threshold', $formdata['stock_threshold']);
		$valuestore->put('price_threshold', $formdata['price_threshold']);
		$valuestore->put('margin_markup', $formdata['margin_markup']); */
		
		
        return redirect(route('admin.modules.livex'));
    }
}
