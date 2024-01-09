<?php

namespace Taksu\Fcm;

use Illuminate\Database\Eloquent\Model;
use Taksu\Restful\Traits\ModelCommonTrait;

class DeviceToken extends Model
{
    use ModelCommonTrait;

    const TYPE_IOS = 'ios';
    const TYPE_ANDROID = 'android';
    const TYPE_OTHER = 'other';

    protected $fillable = [
        'model_type',
        'model_id',
        'device_id',
        'device_type',
        'token',
    ];

    /**
     * The attributes that can be searched
     *
     * @var array
     */
    private static $searchable = [];

    public static function getSearchable() : array
    {
        if (isset($searchable)) {
            return [];
        }
        return static::$searchable;
    }

    public function scopeFindByDeviceId($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    public function deviceTokenable()
    {
        return $this->morphTo();
    }
}
