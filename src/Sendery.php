<?php

namespace Sendery\Laravel;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class Sendery
{
    /** Call once in AppServiceProvider::boot(), after installing and configuring the package. */
    public static function useBuiltInNotifications(
        bool $useNotificationLocale = false,
        string $passwordResetTemplate = 'password-reset',
        string $emailVerificationTemplate = 'email-verification',
    ): void {
        Event::listen(NotificationSending::class, function (NotificationSending $event) use ($useNotificationLocale, $passwordResetTemplate, $emailVerificationTemplate) {
            if ($event->channel !== 'mail') {
                return null;
            }
            $notification = $event->notification;
            // Custom notification subclasses may have different semantics; leave them alone.
            if (get_class($notification) === ResetPassword::class) {
                $url = (new ResetPasswordUrl($notification->token))->actionUrl($event->notifiable);
                $template = $passwordResetTemplate;
                $to = $event->notifiable->getEmailForPasswordReset();
            } elseif (get_class($notification) === VerifyEmail::class) {
                $url = (new VerifyEmailUrl)->actionUrl($event->notifiable);
                $template = $emailVerificationTemplate;
                $to = $event->notifiable->getEmailForVerification();
            } else {
                return null;
            }
            app(Client::class)->send(
                to: $to,
                template: $template,
                data: ['name' => $event->notifiable->name ?? '', 'action_url' => $url],
                locale: $useNotificationLocale ? app()->getLocale() : null,
                idempotencyKey: hash('sha256', ($notification->id ?? (string) Str::uuid()).'|'.$template.'|'.$to),
            );

            return false; // Cancel Laravel's mail channel only after Sendery accepts the request.
        });
    }
}
