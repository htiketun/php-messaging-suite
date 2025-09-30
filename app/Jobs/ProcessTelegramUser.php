<?php

namespace App\Jobs;

use danog\MadelineProto\API;
use Illuminate\Bus\Queueable;
use App\Models\TelegramAccount;
use App\Models\TelegramChatList;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessTelegramUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $accountId;
    public $proto;

    public function __construct($user,  $accountId, $proto)
    {
        $this->user = $user;
        $this->accountId = $accountId;
        $this->proto = $proto;
    }

    public function handle()
    {
        $dir = storage_path("app/telegram/photos/");
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $getUser = $this->proto->getPwrChat($this->user['id']);
        $photoPath = null;
        if (isset($this->user['photo']) && is_array($this->user['photo']) && count($this->user['photo']) > 1) {
            $photoPath = $this->proto->downloadToDir($getUser['photo'], $dir);
            $photoPath = basename($photoPath);
        }

        $userStatus = null;
        if (isset($this->user['status'])) {
            $userStatus = $this->user['status']['_'] ?? null;
            switch ($userStatus) {
                case 'userStatusOnline':
                    $userStatus = 'online';
                    break;
                case 'userStatusOffline':
                    $userStatus = 'offline';
                    break;
                case 'userStatusRecently':
                    $userStatus = 'recently';
                    break;
                case 'userStatusLastWeek':
                    $userStatus = 'last week';
                    break;
                case 'userStatusLastMonth':
                    $userStatus = 'last month';
                    break;
                default:
                    $userStatus = null;
            }
        }

        TelegramChatList::updateOrCreate(
            [
                'telegram_account_id' => $this->accountId,
                'chat_id' => $this->user['id'],
            ],
            [
                'chat_title' => ($this->user['first_name'] ?? '') . ' ' . ($this->user['last_name'] ?? ''),
                'username' => $this->user['username'] ?? null,
                'chat_type' => $this->user['_'] ?? 'other',
                'status' => $userStatus,
                'photo' => $photoPath,
            ]
        );
    }
}
