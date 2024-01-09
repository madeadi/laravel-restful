<?php

namespace Taksu\Restful\Traits;

use Illuminate\Support\Facades\DB;

trait ModelCommonTrait
{

    /**
     * Get list of column name from current model
     *
     * @return array
     */
    public static function getTableColumns(): array
    {
        $tableName = '';
        if (method_exists((new self()), 'getTable')) {
            $tableName = (new self())->getTable();
        }
        return DB::getSchemaBuilder()->getColumnListing($tableName);
    }

    public static function getSearchable(): array
    {
        return [];
    }
}
