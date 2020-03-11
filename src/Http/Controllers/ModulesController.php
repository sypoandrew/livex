<?php

namespace Sypo\Livex\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ModulesController extends Controller
{
    protected $data = []; // the information we send to the view
    protected $user;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware(backpack_middleware());
        $this->user = User::find(Auth::id());
    }

    /**
     * Check permissions
     */
    public function livex(Request $request)
    {
        return view('modules.livex', $this->data);
    }
}
