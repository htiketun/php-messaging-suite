<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramChatHistory extends Model
{
    protected $fillable = [
        'telegram_account_id',
        'chat_id',
        'message_id',
        'sender_id',
        'content',
        'is_outgoing',
        'additional_data',
        'attachments',
        'timestamp',
    ];

    public function telegramAccount()
    {
        return $this->belongsTo(TelegramAccount::class);
    }

    public function chat()
    {
        return $this->belongsTo(TelegramChatList::class, 'chat_id', 'chat_id');
    }
}
