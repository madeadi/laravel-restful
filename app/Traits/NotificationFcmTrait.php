<?php

namespace Taksu\Traits;

use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\AndroidConfig;
use NotificationChannels\Fcm\Resources\AndroidFcmOptions;
use NotificationChannels\Fcm\Resources\AndroidNotification;
use NotificationChannels\Fcm\Resources\ApnsConfig;
use NotificationChannels\Fcm\Resources\ApnsFcmOptions;
use NotificationChannels\Fcm\Resources\Notification;

trait NotificationFcmTrait
{

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setNotification(Notification::create()
                    ->setTitle(data_get($this->toArray($notifiable), 'title'))
                    ->setBody(data_get($this->toArray($notifiable), 'content')))
            ->setAndroid(
                AndroidConfig::create()
                    ->setFcmOptions(AndroidFcmOptions::create()->setAnalyticsLabel('analytics'))
                    ->setNotification(
                        AndroidNotification::create()
                            ->setChannelId('mls-healthcare')
                            ->setSound('default')
                    )
            )->setApns(
            ApnsConfig::create()
                ->setFcmOptions(
                    ApnsFcmOptions::create()
                        ->setAnalyticsLabel('analytics_ios')
                )
                ->setPayload([
                    'aps' => [
                        'sound' => 'default',
                    ],
                ])
        );
    }
}
