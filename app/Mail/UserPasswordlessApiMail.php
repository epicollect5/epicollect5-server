<?php

namespace ec5\Mail;

use ec5\Models\Eloquent\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Carbon\CarbonInterval;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class UserPasswordlessApiMail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $expireAt;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($code)
    {
        $this->code = $code;

        //to show how long the link will last
        $this->expireAt = Carbon::now()
            ->subSeconds(Config::get('auth.passwordless_token_expire', 300))
            ->diffForHumans(Carbon::now(), true);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(Config::get('mail.from.address'), Config::get('mail.from.name'))
            ->subject(trans('site.login_passwordless'))
            ->view('emails.user_passwordless_api');
    }
}
