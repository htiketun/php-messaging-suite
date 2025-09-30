<?php

namespace App\Console\Commands;

use App\Models\TelegramAccount;
use Illuminate\Console\Command;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;

class TelegramFetchAllDialogs extends Command
{
    protected $signature = 'telegram:fetch-all-dialogs {--limit=100} {--user_id=1}';
    protected $description = 'Fetch all Telegram dialogs with pagination using MadelineProto';

    protected function getSessionDir()
    {
        $dir = storage_path("app/telegram_sessions/");
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    protected function getMadelineSettings()
    {
        $settings = new Settings;
        $settings->setAppInfo((new Settings\AppInfo)
                ->setApiId(20724149)
                ->setApiHash('d919f276e10b80ab0b5bf4dad0121663')
        );

        // Use database for session storage
        $settings->setDb((new Settings\Database\Mysql)
                ->setUri('tcp://localhost')
                ->setPassword('')
        );

        return $settings;
    }

    public function handle()
    {
        $userId = (int)$this->option('user_id');
        // Load your MadelineProto session
        $account = TelegramAccount::where('user_id', $userId)->first();
        $api = new API($this->getSessionDir() . $account->session_file, $this->getMadelineSettings());
        $api->start();
        $offset_id = 0;
        $offset_peer = null;
        $limit = $this->option('limit');
        $allDialogs = [];

        do {
            $params = [
            'offset_id'   => $offset_id,
            'offset_peer' => $offset_peer,
            'limit'       => $limit,
            ];

            $result = $api->messages->getDialogs($params);

            if (
            (!isset($result['chats']) || count($result['chats']) === 0) &&
            (!isset($result['users']) || count($result['users']) === 0)
            ) {
            break;
            }

            // Collect chats and users only
            $chats = $result['chats'] ?? [];
            $users = $result['users'] ?? [];

            foreach ($chats as $chat) {
            $allDialogs[] = $chat;
            }
            foreach ($users as $user) {
            $allDialogs[] = $user;
            }

            $this->info('Fetched ' . count($chats) . ' chats in this batch...');
            $this->info('Fetched ' . count($users) . ' users in this batch...');

            // Prepare next offset_id and offset_peer for pagination
            if (isset($result['dialogs']) && count($result['dialogs']) > 0) {
            $lastDialog = end($result['dialogs']);
            $offset_id = $lastDialog['top_message'] ?? 0;
            $offset_peer = $lastDialog['peer'] ?? null;
            } else {
            break;
            }

        } while (isset($result['dialogs']) && count($result['dialogs']) === $limit);

        // Now $allDialogs contains all dialogs, you can process/save them as needed
        $this->info('Total dialogs fetched: ' . count($allDialogs));
        // Example: dump dialogs
        dd($allDialogs);
    }
}
