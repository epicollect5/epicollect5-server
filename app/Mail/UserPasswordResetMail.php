<?php

namespace ec5\Mail;

use ec5\Models\Users\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Carbon\CarbonInterval;
use Carbon\Carbon;

class UserPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $token;
    public $url;
    public $expireAt;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, $token)
    {
        $this->name = $name;
        $this->token = $token;

        $this->url = route('login-reset', ['token' => $token]);

        //to show how long the link will last
        $this->expireAt = Carbon::now()
            ->subSeconds(env('JWT_FORGOT_EXPIRE', 3600))
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
            ->subject(trans('site.reset_password') . ' ' . $this->name)
            ->view('emails.user_reset_password');
    }
}
