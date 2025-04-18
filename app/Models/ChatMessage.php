<?php

namespace HospitalManager\Models;

class ChatMessage extends BaseModel
{
    protected $table = 'hm_chat_messages';

    protected $fillable = [
        'chat_id',
        'sender_id',
        'receiver_id',
        'message',
        'read',
        'created_at'
    ];
}
