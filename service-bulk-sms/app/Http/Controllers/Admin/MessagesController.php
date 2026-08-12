<?php

namespace App\Http\Controllers\Admin;

use App\Models\MessageUpdate;

class MessagesController
{
    public function index()
    {
        $messages = MessageUpdate::with(['message', 'message.provider', 'message.provider.domain'])->orderByDesc('created_at')->simplePaginate(50);

        return view('admin.messages.index', compact('messages'));
    }
}
