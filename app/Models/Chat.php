<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $table = 'chats';
    protected $guarded = ['id'];

    public function participants()
    {
        return $this->hasMany(ChatParticipants::class, 'chat_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'chat_id');
    }

    public function lastMessages()
    {
        return $this->hasOne(ChatMessage::class, 'chat_id')->latest('updated_at');
    }
}
