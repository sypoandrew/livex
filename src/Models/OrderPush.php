<?php

namespace Sypo\Livex\Models;

use Illuminate\Support\Facades\Log;
use Aero\Cart\Models\Order;
use Aero\Common\Models\AdditionalAttribute;
use Sypo\Livex\Models\ErrorReport;

class OrderPush
{
    protected $approved_user_agents = [];
    protected $error_code = 'order_push';
    protected $environment;
    
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct()
    {
        #Liv-ex user agent
        $this->approved_user_agents[] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X x.y; rv:42.0) Gecko/20100101 Firefox/42.0';
        $this->environment = env('LIVEX_ENV');
    }
    
    /**
     * Get approved user agents for authorising notificiations
     *
     * @return array
     */
    public function get_user_agents()
    {
        return $this->approved_user_agents;
    }
    
    /**
     * Check the PUSH request headers are valid
     *
     * @param \Illuminate\Http\Request $request
     * @return boolean
     */
    public function valid_headers(\Illuminate\Http\Request $request)
    {
        if($request->headers->has('user-agent') and in_array($request->headers->get('user-agent'), $this->get_user_agents())){
            return true;
        }
        if($this->environment == 'test'){
            return true;
        }
        return false;
    }
    
    /**
     * Initial PING test from Liv-Ex
     *
     * @param \Illuminate\Http\Request $request
     * @return boolean
     */
    public function ping_test(\Illuminate\Http\Request $request)
    {
        if($this->valid_headers($request)){
            /* $err = new ErrorReport;
            $err->message = 'Successful PING test';
            $err->code = $this->error_code;
            $err->line = __LINE__;
            $err->save(); */
            return true;
        }
        else{
            $err = new ErrorReport;
            $err->message = 'Invalid user agent ' . $request->headers->get('user-agent');
            $err->code = $this->error_code;
            $err->line = __LINE__;
            $err->save();
        }
        return false;
    }
    
    /**
     * Process PUSH request
     *
     * @param \Illuminate\Http\Request $request
     * @return boolean
     */
    public function process_request(\Illuminate\Http\Request $request)
    {
        if($this->valid_headers($request)){
            $data = $request->json()->all();
            #dd($data);
            if(isset($data['trade'])){
                $order = null;
                if(isset($data['trade']['merchant_ref'])){
                    $order = Order::where('reference', $data['trade']['merchant_ref'])->first();
                    if($order !== null){
                        #fix to resolve issue with PUSH notificiation returning too quickly
                        sleep(2);
                        if(isset($data['trade']['order_guid'])){
                            if($order->hasAdditional('livex_guid_'.$data['trade']['order_guid'])){
                                #matched the GUID - let's save the trade id
                                $order->additional('livex_tradeid_'.$data['trade']['trade_id'], $data['trade']['order_guid']);
                            }
                            else{
                                #order guid not found
                                $err = new ErrorReport;
                                $err->message = 'Order GUID '.$data['trade']['order_guid'].' not matched against order. ' . json_encode($request->all());
                                $err->code = $this->error_code;
                                $err->line = __LINE__;
                                $err->order_id = $order->id;
                                $err->save();
                            }
                        }
                        else{
                            #order not found...
                            $err = new ErrorReport;
                            $err->message = 'No order GUID found. ' . json_encode($request->all());
                            $err->code = $this->error_code;
                            $err->line = __LINE__;
                            $err->order_id = $order->id;
                            $err->save();
                        }
                    }
                    else{
                        $err = new ErrorReport;
                        $err->message = 'Order not found ' . $data['trade']['merchant_ref'] . '. ' .  json_encode($request->all());
                        $err->code = $this->error_code;
                        $err->line = __LINE__;
                        $err->save();
                    }
                    
                    return true;
                }
                else{
                    $err = new ErrorReport;
                    $err->message = 'No order reference found. ' . json_encode($request->all());
                    $err->code = $this->error_code;
                    $err->line = __LINE__;
                    $err->save();
                }
            }
            else{
                $err = new ErrorReport;
                $err->message = 'Invalid request. ' . json_encode($request->all());
                $err->code = $this->error_code;
                $err->line = __LINE__;
                $err->save();
            }
        }
        else{
            $err = new ErrorReport;
            $err->message = 'Invalid user agent ' . $request->headers->get('user-agent') . '. ' .  json_encode($request->all());
            $err->code = $this->error_code;
            $err->line = __LINE__;
            $err->save();
        }
        return false;
    }
}
