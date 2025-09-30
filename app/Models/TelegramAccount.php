<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramAccount extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'phone', 'session_file', 'username', 'first_name', 'photo'];

    protected $appends = ['photo_url'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chatLists()
    {
        return $this->hasMany(TelegramChatList::class);
    }

    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return asset('storage/telegram/photos/' . $this->photo);
        }
        return null;
    }
}
