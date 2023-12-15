<?php

namespace ec5\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;

class ExceptionNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $exceptionMessage;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($exceptionMessage)
    {
        //imp: do not use "message" as variable name as that is reserved
        //https://laravel.com/docs/5.0/mail#basic-usage
        $this->exceptionMessage = $exceptionMessage;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Server Exception')
            ->view('emails.exception_notification');
    }
}
