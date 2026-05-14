<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Certify LMS パスワード再設定のご案内')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line('パスワード再設定のリクエストを受け付けました。')
            ->action('パスワードを再設定する', $url)
            ->line('このリンクは '.config('auth.passwords.users.expire').' 分間有効です。')
            ->line('心当たりがない場合は、このメールを破棄してください。')
            ->salutation('Certify LMS 運営チーム');
    }
}
