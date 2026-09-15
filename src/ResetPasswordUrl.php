<?php

namespace Sendry\Laravel;

use Illuminate\Auth\Notifications\ResetPassword;

class ResetPasswordUrl extends ResetPassword
{
    public function actionUrl(mixed $notifiable): string
    {
        return $this->resetUrl($notifiable);
    }
}
