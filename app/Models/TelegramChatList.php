<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramChatList extends Model
{
    protected $table = 'telegram_chat_list';

    protected $fillable = [
        'telegram_account_id',
        'chat_id',
        'chat_title',
        'username',
        'chat_type',
        'status',
        'photo',
        'last_message',
        'last_message_date',
        'participants_count',
    ];

    protected $appends = ['photo_url'];

    public function telegramAccount()
    {
        return $this->belongsTo(TelegramAccount::class);
    }

    public function chatHistories()
    {
        return $this->hasMany(TelegramChatHistory::class, 'chat_id', 'chat_id');
    }

    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return asset('storage/telegram/photos/' . $this->photo);
        }
        return null;
    }
}
