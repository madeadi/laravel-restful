# Laravel Restful

# To use
Create a controller class and extends it from `CrudController`. 

Example: 

use `CommonModelTrait` on the model. 

```php
class Admin extends Model
{
    use ModelCommonTrait;
    ...
}
```


```php

namespace App\Http\Controllers;

use App\Models\Admin;
use Taksu\Restful\Controllers\CrudController;

class AdminController extends CrudController
{
    public function __construct()
    {
        parent::__construct(Admin::class);
    }
}
```

Add in the `routes\api.php` 

```
Route::apiResource('admins', AdminController::class);
```

Finally, query the API

```
GET localhost:8000/api/admins
```


To install the console commands, in `AppServiceProvider`, add the following: 

```
public function boot()
{
    if ($this->app->runningInConsole()) {
        $this->commands([
            MakeCrudController::class,
        ]);
    }
}
```