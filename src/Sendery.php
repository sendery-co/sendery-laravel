<?php

namespace Sendery\Laravel;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;

class Sendery
{
    /** Call once in AppServiceProvider::boot(). Delivery uses Laravel's configured mailer. */
    public static function useBuiltInNotifications(
        bool $useNotificationLocale = false,
        string $passwordResetTemplate = 'password-reset',
        string $emailVerificationTemplate = 'email-verification',
    ): void {
        ResetPassword::toMailUsing(function ($notifiable, string $token) use ($useNotificationLocale, $passwordResetTemplate): TemplateMail {
            $url = (new ResetPasswordUrl($token))->actionUrl($notifiable);
            $to = $notifiable->getEmailForPasswordReset();

            return (new TemplateMail($passwordResetTemplate, ['name' => $notifiable->name ?? '', 'action_url' => $url],
                $useNotificationLocale ? app()->getLocale() : null, hash('sha256', $passwordResetTemplate.'|'.$to.'|'.$url)))->to($to);
        });
        VerifyEmail::toMailUsing(function ($notifiable, string $url) use ($useNotificationLocale, $emailVerificationTemplate): TemplateMail {
            $to = $notifiable->getEmailForVerification();

            return (new TemplateMail($emailVerificationTemplate, ['name' => $notifiable->name ?? '', 'action_url' => $url],
                $useNotificationLocale ? app()->getLocale() : null, hash('sha256', $emailVerificationTemplate.'|'.$to.'|'.$url)))->to($to);
        });
    }
}
