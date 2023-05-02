<?php

namespace Tests;

use ec5\Mail\UserAccountDeletionUser;
use ec5\Mail\UserAccountDeletionAdmin;
use ec5\Models\Users\User;
use Illuminate\Support\Facades\Mail;

class InternalRoutesTest extends TestCase
{
    //internal routes use the default 'web; guard
    const DRIVER = 'web';

    protected $privateProjectSlug;
    protected $publicProjectSlug;

    public function setup()
    {
        parent::setUp();
        $this->privateProjectSlug = 'ec5-private';
        $this->publicProjectSlug = 'ec5-public';
    }

    /**
     * Test an authenticated user's routes
     */
    public function testPrivateInternalRoutes()
    {
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->email = env('CREATOR_EMAIL');
        $user->id = env('CREATOR_ID');

        //private project
        $this->actingAs($user, SELF::DRIVER)
            ->json('GET', 'api/internal/project/ec5-private', [])
            ->assertStatus(200);

        //account deletion request    
        Mail::fake();
        $this->actingAs($user, SELF::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" =>  [
                    "id" => "account-deletion-request",
                    "accepted" => true
                ]
            ]);

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionUser::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
        //assert a message was sent to admin
        Mail::assertSent(UserAccountDeletionAdmin::class, function ($mail) {
            return $mail->hasTo(env('SYSTEM_EMAIL'));
        });
    }

    public function testPublicInternalRoutes()
    {
        $slug = $this->publicProjectSlug;

        $this->json('GET', 'api/project/' . $slug, [])
            ->assertStatus(200);

        //api/internal/media/{project_slug}
        $this->json('GET', 'api/internal/media/' . $slug, [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "type"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "format"
                    ]
                ]
            ]);

        $this->json('GET', 'api/internal/media/' . $slug . '?type=photo', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "format"
                    ]
                ]
            ]);

        $this->json('GET', 'api/internal/media/' . $slug . '?type=photo&format=entry_thumb', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ]
                ]
            ]);


        //gives back default placeholder photo
        $this->json('GET', 'api/internal/media/' . $slug . '?type=photo&format=entry_thumb&name=ciao', [])
            ->assertStatus(200);

        //api/internal/media/{project_slug}
        $this->json('GET', 'api/internal/temp-media/' . $slug, [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "type"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "format"
                    ]
                ]
            ]);

        $this->json('GET', 'api/internal/temp-media/' . $slug . '?type=photo', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ],
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "format"
                    ]
                ]
            ]);

        $this->json('GET', 'api/internal/temp-media/' . $slug . '?type=photo&format=entry_thumb', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "name"
                    ]
                ]
            ]);


        //gives back default placeholder photo
        $this->json('GET', 'api/internal/temp-media/' . $slug . '?type=photo&format=entry_thumb&name=ciao', [])
            ->assertStatus(200);

        $this->json('GET', 'api/internal/download-entries/' . $slug . '', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_86",
                        "title" => "User not authenticated.",
                        "source" => "download-entries"
                    ]
                ]
            ]);
        $this->json('GET', 'api/internal/download-entries/' . $slug . '?filter_by=created_at&format=csv&map_index=0&epicollect5-download-entries=1682589106048', [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_86",
                        "title" => "User not authenticated.",
                        "source" => "download-entries"
                    ]
                ]
            ]);
    }
}
