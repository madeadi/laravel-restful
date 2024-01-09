<?php

namespace Taksu\Restful\Controllers;

use Exception;
use function GuzzleHttp\json_decode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Taksu\Restful\Controllers\Controller;
use Taksu\Restful\Traits\ModelCommonTrait;

class CrudController extends Controller
{
    public function __construct(protected $model, protected $resourceClass = null, protected $relations = [], protected $createAction = null, protected $updateAction = null, protected $deleteAction = null)
    {
    }

    public function index(Request $request)
    {
        $data = $request->all();

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

    public function show($id)
    {
        $model = $this->model::findOrFail($id);
        return $this->resource($model);
    }

    public function store(Request $request)
    {
        $model = null;
        if ($this->createAction) {
            $model = $this->createAction::run($request->all());
        } else {
            $model = $this->model::create($request->all());
        }

        return response()->json($this->resource($model), 201);
    }

    public function update(Request $request, $id)
    {
        $model = $this->model::findOrFail($id);
        $model->fill($request->all());
        $model->save();
        return response()->json($this->resource($model), 200);
    }

    public function destroy($id)
    {
        $deleteResult = $this->model::destroy($id) ? true : false;
        $status = $deleteResult ? 200 : 500;
        $message = $deleteResult ? __('Success') : __('Failed. Please try again in few moment');
        return response()->json([
            'message' => $message,
        ], $status);
    }

    /**
     * A helper function to add a filter in Request->all()
     *
     * @param array $data
     * @param type $filterKey
     * @param type $filterValue
     */
    protected function addFilter(Request &$request, $filterKey, $filterValue)
    {
        $filters = $this->parseFilter($request);
        $data = $request->all();

        $filters[$filterKey] = $filterValue;
        $data["filter"] = json_encode($filters);
        $request->replace($data);
    }

    /**
     * A helper function to remove value from filter in Request->all()
     *
     * @param Request $request
     * @param [type] $filterKey
     * @return void
     */
    protected function removeFilter(Request &$request, $filterKey)
    {
        $filters = $this->parseFilter($request);
        unset($filters[$filterKey]);
        $data["filter"] = json_encode($filters);
        $request->replace($data);
    }

    protected function getFilterValue(Request $request, $filterKey)
    {
        $filters = $this->parseFilter($request);
        return data_get($filters, $filterKey);
    }

    private function parseFilter(Request &$request)
    {
        $data = $request->all();
        $originalFilter = data_get($data, 'filter', []);
        $filters = is_string($originalFilter) ? json_decode($originalFilter, true) : $originalFilter;
        return $filters;
    }

    /**
     * A helper function to return resource/model with relations
     */
    public function resource($model)
    {
        $model->load($this->relations);
        return ($this->resourceClass) ? new $this->resourceClass($model) : $model;
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

        // The key is the column in the database table. For special case, the key can be a relation column.
        // The value is an object that can contain operator, function, and value. Can choose to use operator or function.
        // The operator is a Mysql Comparison Operators
        foreach ($filters as $filterColumn => $filterValue) {
            // The format of the relation column is "relationTable.relationColumn". Please note the dot.
            // Example of using contain relation:
            // http://localhost:8000/api/some-module?filter={"schedules.schedule_id": {"operator": "=", "value": "01gy9d4rhw1j2v2nwrcmnnmzr7"}}
            $delimiter = '.';
            if (strpos($filterColumn, $delimiter) !== false) {
                // get text before last . (dot)
                $relationTable = substr($filterColumn, 0, strrpos($filterColumn, $delimiter));

                // get text after last . (dot)
                $relationColumn = substr($filterColumn, strrpos($filterColumn, $delimiter) + 1);

                $builder->whereHas($relationTable, function ($query) use ($relationColumn, $filterValue) {
                    // if filter value is string, we need to check if it's in or not in
                    if (is_string($filterValue)) {
                        $query->where($relationColumn, '=', $filterValue);
                    } else {
                        if ($filterValue['operator'] === 'in') {
                            $query->whereIn($relationColumn, explode(',', $filterValue['value']));
                        } else {
                            $query->where($relationColumn, $filterValue['operator'] ?? '=', $filterValue['value']);
                        }
                    }
                });
            }
            else {
                // The value can contain a function.
                // The value for function can be one of these: date, time, day, month, year, in, and between. 
                // This will be converted into Laravel query builder.
                // Example of using:
                // http://localhost:8000/api/some-module?filter={"time_in": {"function": "month", "value": "04"}}
                if (!empty($filterValue['function'])) {
                    switch ($filterValue['function']) {
                        case 'date':
                            $builder->whereDate($filterColumn, $filterValue['value']);
                            break;

                        case 'time':
                            $builder->whereTime($filterColumn, $filterValue['value']);
                            break;

                        case 'day':
                            $builder->whereDay($filterColumn, $filterValue['value']);
                            break;
                        
                        case 'month':
                            $builder->whereMonth($filterColumn, $filterValue['value']);
                            break;
                            
                        case 'year':
                            $builder->whereYear($filterColumn, $filterValue['value']);
                            break;

                        case 'in':
                            // the value is comma separated, e.g 1,3,7,9
                            $values = explode(',', $filterValue['value']);
                            $builder->whereIn($filterColumn, $values);
                            break;

                        case 'between':
                            // the value is comma separated, e.g 1,5
                            // can be a date also, e.g 2023-01-01,2023-01-31
                            $values = explode(',', $filterValue['value']);
                            $builder->whereBetween($filterColumn, $values);
                            break;
                        
                        default:
                            $builder;
                            break;
                    }
                }
                else {
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
}
