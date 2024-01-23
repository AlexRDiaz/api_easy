<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserValidation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */

    public $resultCode;


    /**
     * Create a new message instance.
     *
     * @param  string  $resultCode
     * @return void
     */
    public function __construct($resultCode)
    {
        $this->resultCode = $resultCode;
      
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    // public function content(){
    //     return new Content(
    //         view:  'emails.validation_code',
    //     );
    // }
    public function build()
    {
        return $this
            ->to('')
            ->subject('Código de Validación')
            ->view('emails.user_validation');
    }
    
}
