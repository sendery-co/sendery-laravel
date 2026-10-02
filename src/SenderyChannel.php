<?php

namespace Sendery\Laravel;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class SenderyChannel
{
    public function __construct(private Client $client) {}

    public function send(object $notifiable, Notification $notification): array
    {
        $data = $notification->toSendery($notifiable);
        $recipient = $notifiable->routeNotificationFor('mail', $notification);
        if (! is_string($recipient)) {
            throw new \InvalidArgumentException('Sendery requires one email address.');
        }
        $notification->id ??= (string) Str::uuid();

        return $this->client->send($recipient, $data['template'], $data['data'], $data['locale'] ?? null,
            $data['idempotency_key'] ?? hash('sha256', $notification->id.'|'.$data['template'].'|'.$recipient), $data['attachments'] ?? [], $data['version'] ?? null);
    }
}
