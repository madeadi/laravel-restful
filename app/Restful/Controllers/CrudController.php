<?php

namespace Taksu\Restful\Controllers;

use Exception;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;
use Illuminate\Http\Request;
use Taksu\Restful\Controllers\Controller;

class CrudController extends Controller
{
    protected $service;
    protected $resource;
    protected $resourceName;

    protected $resourceClass;
    /** @var string[] */
    public $relations = [];

    public function __construct(Request $request)
    {
        $this->resourceName = $this->resolveResourceName($request->path());
        // $this->service = ServiceFactory::get($this->resourceName);

    }

    private function resolveResourceName(string $path)
    {
        // example of the path: /api/customer/:id, or /api/customers/:id
        // therefore, we get the second string
        $arr = explode("/", $path);
        $name = "";
        if (sizeof($arr) < 1) {
            throw new Exception("resolveResourceName is not correct.");
        } elseif (sizeof($arr) > 1 && $arr[1]) {
            $name = $arr[1];
        }

        return $name;
    }

    public function index(Request $request)
    {
        return $this->service->findAll($request->all());
    }

    public function show($id)
    {
        $model = $this->service->findOne($id);
        return $this->resource($model);
    }

    public function store(Request $request)
    {
        $body = $request->all();

        // Store
        $model = $this->service->create($body);

        // Return
        return response()->json($this->resource($model), 201);
    }

    public function update(Request $request, $id)
    {
        $body = $request->all();

        // Update
        $model = $this->service->update($id, $body);

        // Return
        return response()->json($this->resource($model), 200);
    }

    public function destroy($id)
    {
        // Delete resource
        $deleteResult = $this->service->delete($id);
        $status = $deleteResult ? 200 : 500;
        $message = $deleteResult ? __('Success') : __('Failed. Please try again in few moment');

        // Return response
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
     * @param type $model
     * @return type
     */
    public function resource($model)
    {
        $model->load($this->relations);
        return ($this->resourceClass) ? new $this->resourceClass($model) : $model;
    }
}
