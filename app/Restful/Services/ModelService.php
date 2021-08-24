<?php

namespace Taksu\Restful\Services;

use Exception;
use function GuzzleHttp\json_decode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Taksu\Restful\Traits\ModelCommonTrait;

class ModelService
{
    protected $model;
    public $name;

    /** @var string[] */
    public $relations;

    /** @var JsonResource */
    protected $resourceClass;

    public function __construct(string $modelClassName)
    {
        $this->model = $modelClassName;
        $this->name = "ModelService";
        $this->relations = [];
    }

    /**
     *
     * @param array $data contains filter, pagination, etc
     * @return LengthAwarePaginator
     * @throws Exception
     */
    public function findAll($data)
    {
        // Query
        /* @var $models Builder */
        $models = $this->model::query()->with($this->relations);
        $columns = $this->model::getTableColumns();
        $traits = class_uses($this->model);

        // build the filters
        $this->filterAll($models, $this->model, $data);

        // Check columns for current table
        // Make sure $filter->sort exists in current table columns
        // If not exists, set $filter->sort to created_at
        $sort = Arr::get($data, 'sort', 'created_at');
        if (!in_array($sort, $columns)) {
            $sort = 'created_at';
        }

        $order = Arr::get($data, 'order', 'desc');
        if (array_key_exists(ModelCommonTrait::class, $traits)) {
            $models->orderBy($sort, strtolower($order));
        }

        // Finally, paginate and return
        $limit = Arr::get($data, 'limit', 20);
        $paginated = $models->paginate($limit);

        if ($this->resourceClass) {
            return $this->resourceClass::collection($paginated);
        }

        return $paginated;
    }

    public function findOne($id)
    {
        return $this->model::findOrFail($id);
    }

    public function create($data)
    {
        return $this->model::create($data);
    }

    public function update($id, $data)
    {
        $model = $this->model::findOrFail($id);
        $model->fill($data);
        $model->save();

        return $model;
    }

    /**
     * Delete one resource
     *
     * @param integer|string $id
     * @return bool
     */
    public function delete($id): bool
    {
        return $this->model::destroy($id) ? true : false;
    }

    /**
     * @param Builder $builder
     * @param $model
     * @param $data
     * @throws Exception
     */
    protected function filterAll(Builder &$builder, $model, $data): void
    {
        // the class must use CommonClass traits so it can handle search
        // and filter operation. If it's not using CommonClass trait,
        // return exception.
        $traits = class_uses($model);
        if (!array_key_exists(ModelCommonTrait::class, $traits)) {
            throw new Exception('Resource needs to use CommonClass Trait', 500);
        }

        // search the columns in DB table using ModelCommonTrait's functions
        $columns = $model::getTableColumns();
        $searchFields = $model::getSearchable();

        // filter based on exact match of fields in the database
        // filter is string. We need to json_decode it into an array.
        $filter = Arr::get($data, 'filter', []);
        $filters = is_string($filter) ? json_decode($filter, true) : $filter;
        foreach ($filters as $filterColumn => $filterValue) {
            if ($filterColumn == 'id') {
                // if $data contains id string, then filter based on primary keys (ids)
                // e.g. filter: {"id":"20,22,24,26,28,30,32,34,36,38"}
                $primaryKeyName = (new $model)->getKeyName();
                $ids = explode(",", $filterValue);
                $builder->whereIn($primaryKeyName, $ids);
            } elseif ($filterColumn == 'created_date_from') {
                if (array_search('created_at', $columns) !== false) {
                    $builder->whereDate('created_at', '>=', $filterValue);
                }
            } elseif ($filterColumn == 'created_date_to') {
                if (array_search('created_at', $columns) !== false) {
                    $builder->whereDate('created_at', '<=', $filterValue);
                }
            } elseif ($filterColumn == 'status') {
                // Filter for one or many statuses
                if (array_search($filterColumn, $columns) !== false) {
                    $statuses = explode(',', $filterValue);
                    $builder->whereIn($filterColumn, $statuses);
                }
            } elseif (array_search($filterColumn, $columns) !== false) {
                // otherwise, filter normally based on the table's columns
                $builder->where($filterColumn, "=", $filterValue);
            }
        }

        // if data contains search string,
        // or, search string can also be embedded in param "q" in filters
        // then, find the string in the Model's searchable fields
        $search = Arr::get($filters, 'search');
        if ($search && $search != '') {
            if (count($searchFields) == 1) {
                $builder->where($searchFields[0], 'like', '%' . $search . '%');
            }

            if (count($searchFields) > 1) {
                $builder->where(function ($query) use ($searchFields, $search) {
                    foreach ($searchFields as $index => $searchField) {
                        if ($index == 0) {
                            $query->where($searchField, 'like', '%' . $search . '%');
                        } else {
                            $query->orWhere($searchField, 'like', '%' . $search . '%');
                        }
                    }
                });
            }
        }
    }

    /**
     * A helper function to return resource/model with relations
     * @param type $model
     * @return type
     */
    public function resource($model)
    {
        $model->load($this->relations);
        return ($this->resourceClass) ? new $this->resourceClass($model) : $model;
    }

    protected function getFilterValue($data, $filterKey)
    {
        $originalFilter = data_get($data, 'filter', []);
        $filters = is_string($originalFilter) ? json_decode($originalFilter, true) : $originalFilter;
        return data_get($filters, $filterKey);
    }
}
