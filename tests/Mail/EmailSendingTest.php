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
        $email = config('testing.SUPER_ADMIN_EMAIL');

        Mail::to($email)->send(new DebugEmailSending());

        $this->assertTrue(true);
    }
}
