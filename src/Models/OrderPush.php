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
    protected $wait_time = 2;
    
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
            $stored_request = file_put_contents(storage_path('logs/order_push_log/log-'.\Carbon\Carbon::now()->format('Y-m-d-H-i-s').'.json'), json_encode($request->json()->all()));
            $data = $request->json()->all();
            #dd($data);
            if(isset($data['trade'])){
                return $this->confirm_trade($request);
            }
            elseif(isset($data['order'])){
                return $this->order_update($request);
            }
            else{
                $err = new ErrorReport;
                $err->message = 'Invalid request. ' . json_encode($request->all());
                $err->code = $this->error_code;
                $err->line = __LINE__;
                $err->save();
            }
            return true;
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
    
    /**
     * Process confirm trade PUSH request
     *
     * @param \Illuminate\Http\Request $request
     * @return boolean
     */
    protected function confirm_trade(\Illuminate\Http\Request $request)
    {
		$data = $request->json()->all();
		#dd($data);
		if(isset($data['trade'])){
			$order = null;
			if(isset($data['trade']['merchant_ref'])){
				$order = Order::where('reference', $data['trade']['merchant_ref'])->first();
				if($order !== null){
					#fix to resolve issue with PUSH notificiation returning too quickly
					sleep($this->wait_time);
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
			}
			else{
				$err = new ErrorReport;
				$err->message = 'No order reference found. ' . json_encode($request->all());
				$err->code = $this->error_code;
				$err->line = __LINE__;
				$err->save();
			}
		}
		
		return true;
	}
    
    /**
     * Process order update PUSH request
     *
     * @param \Illuminate\Http\Request $request
     * @return boolean
     */
    protected function order_update(\Illuminate\Http\Request $request)
    {
		$data = $request->json()->all();
		#dd($data);
		if(isset($data['order'])){
			$order = null;
			if(isset($data['order']['merchant_ref'])){
				$order = Order::where('reference', $data['order']['merchant_ref'])->first();
				if($order !== null){
					#fix to resolve issue with PUSH notificiation returning too quickly
					sleep($this->wait_time);
					if(isset($data['order']['order_status']) and ($data['order']['order_status'] == 'Suspended' or $data['order']['order_status'] == 'Deleted')){
						if(isset($data['order']['order_guid'])){
							if($order->hasAdditional('livex_guid_'.$data['order']['order_guid'])){
								#matched the GUID - let's save the status for future refund process_request
								$status = strtolower($data['order']['order_status']);
								$order->additional('livex_' . $status . '_' . $data['order']['order_guid'], $data['order']['lwin']);
							}
							else{
								#order guid not found
								$err = new ErrorReport;
								$err->message = 'Order GUID '.$data['order']['order_guid'].' not matched against order. ' . json_encode($request->all());
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
						#order not found...
						$err = new ErrorReport;
						$err->message = 'Status notification for manual review. ' . json_encode($request->all());
						$err->code = $this->error_code;
						$err->line = __LINE__;
						$err->order_id = $order->id;
						$err->save();
					}
				}
				else{
					$err = new ErrorReport;
					$err->message = 'Order not found ' . $data['order']['merchant_ref'] . '. ' .  json_encode($request->all());
					$err->code = $this->error_code;
					$err->line = __LINE__;
					$err->save();
				}
			}
			else{
				$err = new ErrorReport;
				$err->message = 'No order reference found. ' . json_encode($request->all());
				$err->code = $this->error_code;
				$err->line = __LINE__;
				$err->save();
			}
		}
		
		return true;
	}
}
