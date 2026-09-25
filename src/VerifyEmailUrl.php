<?php

namespace Sendary\Laravel;

use Illuminate\Auth\Notifications\VerifyEmail;

class VerifyEmailUrl extends VerifyEmail
{
    public function actionUrl(mixed $notifiable): string
    {
        return $this->verificationUrl($notifiable);
    }
}
