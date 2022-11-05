# Laravel Restful

# To use
Create a controller class and extends it from `CrudController`. 

Example: 

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