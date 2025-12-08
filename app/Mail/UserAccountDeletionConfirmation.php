<?php

namespace ec5\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserAccountDeletionConfirmation extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $email;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($email)
    {
        $this->email = $email;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->from(
            config('mail.from.address'),
            config('mail.from.name')
        )
            ->subject('Account Deletion Confirmation')
            ->view('emails.account_deletion_confirmation');
    }
}
