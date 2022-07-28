<?php

namespace ec5\Mail;

use ec5\Models\Users\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Carbon\CarbonInterval;
use Carbon\Carbon;

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
            ->subSeconds(env('PASSWORDLESS_TOKEN_EXPIRES_IN', 300))
            ->diffForHumans(Carbon::now(), true);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->subject(trans('site.login_passwordless'))
            ->view('emails.user_passwordless_api');
    }
}
