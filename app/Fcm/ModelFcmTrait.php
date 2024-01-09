<?php

namespace Taksu\Fcm;

use Illuminate\Support\Arr;

trait ModelFcmTrait
{
    public function deviceTokens()
    {
        return $this->morphMany(DeviceToken::class, 'deviceTokenable', 'model_type', 'model_id');
    }

    /**
     * Specifies the user's FCM token
     *
     * @return string
     */
    public function routeNotificationForFcm()
    {
        return Arr::get($this->deviceTokens->last(), 'token');
    }
}
