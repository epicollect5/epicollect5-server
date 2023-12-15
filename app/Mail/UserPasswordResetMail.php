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
            ->subSeconds(config('auth.jwt-forgot.expire'))
            ->diffForHumans(Carbon::now(), true);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(trans('site.reset_password') . ' ' . $this->name)
            ->view('emails.user_reset_password');
    }
}
