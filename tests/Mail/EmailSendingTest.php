<?php

namespace Tests\Mail;

use ec5\Mail\DebugEmailSending;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailSendingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_send_email()
    {
        // Fake the mail system
        Mail::fake();

        $email = config('testing.SUPER_ADMIN_EMAIL');

        // Trigger the email
        Mail::to($email)->send(new DebugEmailSending());

        // Assert the email was sent
        Mail::assertSent(DebugEmailSending::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }
}
