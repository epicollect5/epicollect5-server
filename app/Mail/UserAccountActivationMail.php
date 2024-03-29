<?php

namespace ec5\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserAccountActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $code;
    public $url;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, $code)
    {
        $this->name = $name;
        $this->code = $code;
        $this->url = route('verify');
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(trans('site.activate_your_account') . ' ' . $this->name)
            ->view('emails.user_registration');
    }
}
