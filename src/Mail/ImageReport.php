<?php

namespace Sypo\Livex\Mail;

use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;


class ImageReport extends Mailable
{
    use Queueable, SerializesModels;
	
    public $products;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($products)
    {
        $this->products = $products;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
		$this
		->subject('VinQuinn missing image report')
		->to('andrew@sypo.co.uk')
		->from('andrew@sypo.co.uk', 'Andrew Tanner')
		->replyTo('andrew@sypo.co.uk', 'Andrew Tanner')
		->markdown('livex::emails.imagereport');
		
		return $this;
    }
}
