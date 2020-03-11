<?php

namespace Sypo\Livex\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Aero\Admin\Facades\Admin;
use Aero\Admin\Http\Controllers\Controller;

class ModulesController extends Controller
{
    protected $data = []; // the information we send to the view

    /**
     * Show main settings form
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return view('modules.livex', $this->data);
    }
    
	/**
     * Update settings
     *
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
		$formdata = $request->json()->all();
		Log::debug($formdata);
		
		
		
        return redirect(route('admin.modules.livex'));
    }
}
