<?php

namespace ec5\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'ec5\Events\SomeEvent' => [
            'ec5\Listeners\EventListener',
        ],
    ];

    /**
     * Register any other events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        //
    }
}
