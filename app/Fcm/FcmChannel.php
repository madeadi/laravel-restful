<?php

namespace Taksu\Fcm;

use NotificationChannels\Fcm\FcmChannel as Base;
use Illuminate\Notifications\Notification;

/**
 * Override FcmChannel because we want to do nothing, if
 * Notifiable doesn't contain any token.
 *
 * @author madeadi
 */
class FcmChannel extends Base
{

    /**
     * Do nothing when notifiable's token is empty
     *
     * @param mixed $notifiable
     * @param Notification $notification
     *
     * @return array
     * @throws \NotificationChannels\Fcm\Exceptions\CouldNotSendNotification
     */
    public function send($notifiable, Notification $notification)
    {
        $token = $notifiable->routeNotificationFor('fcm');
        if (empty($token)) {
            return [];
        }

        return parent::send($notifiable, $notification);
    }
}
