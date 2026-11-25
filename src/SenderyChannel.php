<?php

namespace Sendery\Laravel;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class SenderyChannel
{
    public function send(object $notifiable, Notification $notification): mixed
    {
        return Mail::send(TemplateMail::fromNotification($notifiable, $notification));
    }
}
