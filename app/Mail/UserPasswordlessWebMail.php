<?php

namespace ec5\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class UserPasswordlessWebMail extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $token;
    public $url;
    public $expireAt;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($token, $email)
    {
        $this->token = $token;
        //enter invisible char to avoid email client to render the email as clickable
        //see https://next-auth.js.org/providers/email
        $this->email = preg_replace('/\./', '&#8203;.', $email);
        $this->url = route('passwordless-authenticate-web', ['token' => $token]);

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
            ->view('emails.user_passwordless_web');
    }
}
