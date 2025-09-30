<?php

namespace App\Listeners;

use App\Models\TelegramAccount;
use App\Jobs\FetchTelegramDialogs;
use App\Services\MadelineProtoService;
use App\Events\TelegramChatListFetchRequested;

class FetchAndQueueTelegramChatList
{
    protected MadelineProtoService $madelineService;

    public function __construct(MadelineProtoService $madelineService)
    {
        $this->madelineService = $madelineService;
    }

    public function handle(TelegramChatListFetchRequested $event)
    {
        $proto = $this->madelineService->getInstance($event->accountId);
        $account = TelegramAccount::where('session_file', $event->accountId)->first();
        if (!$account) {
            return;
        }

        FetchTelegramDialogs::dispatch($account->id, $proto)->delay(now()->addSeconds(1));
    }
}


// # Check if proc_open is enabled

// php -i | grep proc_open



// # Check open_basedir restrictions

// php -i | grep open_basedir



// # Compare CLI and web PHP versions

// php -v

// # Then, create a PHP file with <?php phpinfo(); and open it in your browser to compare versions



// # If proc_open is disabled, edit your php.ini to remove it from the disable_functions list:

// # Open php.ini and find the line:

// # disable_functions = ...

// # Remove proc_open from this list and restart your web server



// # If open_basedir is set, comment it out or add your project directory to it in php.ini:

// # open_basedir =



// # Restart your web server after making changes
