<?php

namespace App\Providers;

use App\Events\TelegramChatListFetchRequested;
use App\Listeners\FetchAndQueueTelegramChatList;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<int, array<int, class-string>>
     */
    protected $listen = [

        TelegramChatListFetchRequested::class => [
            FetchAndQueueTelegramChatList::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
