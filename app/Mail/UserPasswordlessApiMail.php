<?php

namespace ec5\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserPasswordlessApiMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $code;
    public string $expireAt;

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
            ->subSeconds(config('auth.passwordless_token_expire', 300))
            ->diffForHumans(Carbon::now(), true);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(trans('site.login_passwordless'))
            ->view('emails.user_passwordless_api');
    }
}
