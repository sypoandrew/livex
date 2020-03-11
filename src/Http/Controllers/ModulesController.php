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
     * Check permissions
     */
    public function livex(Request $request)
    {
        return view('modules.livex', $this->data);
    }
}
