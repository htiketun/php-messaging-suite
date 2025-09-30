<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\TelegramChatList;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessTelegramChat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $chat;
    public $accountId;
    public $proto;

    public function __construct($chat,  $accountId, $proto)
    {
        $this->chat = $chat;
        $this->accountId = $accountId;
        $this->proto = $proto;
    }

    public function handle()
    {
        $dir = storage_path("app/telegram/photos/");
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $getChat = $this->proto->getPwrChat($this->chat['id']);
        $photoPath = null;
        if (isset($this->chat['photo']) && is_array($this->chat['photo']) && count($this->chat['photo']) > 1) {
            $photoPath = $this->proto->downloadToDir($getChat['photo'], $dir);
            $photoPath = basename($photoPath);
            
        }
        TelegramChatList::updateOrCreate(
            [
                'telegram_account_id' => $this->accountId,
                'chat_id' => $this->chat['id'],
            ],
            [
                'chat_title' => $this->chat['title'] ?? null,
                'username' => $this->chat['username'] ?? null,
                'chat_type' => $this->chat['_'] ?? 'other',
                'photo' => $photoPath,
                'participants_count' => $getChat['participants_count'] ?? 0,
            ]
        );
    }
}
