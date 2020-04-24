<?php

namespace Sypo\Livex\Mail;

use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Sypo\Livex\Models\EmailNotification;


class ImageReportMail extends Mailable
{
    use Queueable, SerializesModels;
	
    public $products;
    public $code;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($products, $code)
    {
        $this->products = $products;
        $this->code = $code;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
		$notify = new EmailNotification;
		$user = \Auth::user();
		if($user){
			$notify->admin_id = $user->id;
		}
		$notify->code = $this->code;
		$notify->save();
		
		$this
		->subject('VinQuinn '.str_replace('_', ' ', $this->code))
		->to(setting('Livex.image_report_send_to_email'))
		->from(setting('Livex.image_report_send_from_email'), setting('Livex.image_report_send_from_name'))
		->markdown('livex::emails.'.$this->code);
		
		return $this;
    }
}
