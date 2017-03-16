# Jqgrid for Laravel 5

## Install

Via Composer

``` bash
$ composer require webmachine/jqgrid
```

Next, you must install the service provider and facade alias:

```php
// config/app.php
'providers' => [
    ...
    Webmachine\Jqgrid\JqgridServiceProvider::class,
];

...

'aliases' => [
    ...
    'Jqgrid' => Webmachine\Jqgrid\JqgridFacade::class,
];
```

Publish

``` bash
$ php artisan vendor:publish --provider="Webmachine\Jqgrid\JqgridServiceProvider"
```

## Usage

In your Controller
``` php
...
use Webmachine\Jqgrid\JqgridFacade as Jqgrid;

class FooController extends Controller {

    /**
     * @see http://www.trirand.com/jqgridwiki/doku.php?id=wiki:colmodel_options ColModel API.
     */
    const jqgrid_colmodel = [
        [
            'label' => 'Id',
            'name' => 'id',
            'hidden' => true
        ],        
        [
            'label' => 'Number',
            'name' => 'number',
            'searchoptions' => [
                'sopt' => ['bw']
            ]            
        ],
        [
            'label' => 'Name',
            'name' => 'name',
        ],       
        [
            'label' => 'Provider',
            'name' => 'providers.name',
            'relation' => 'FooModel.provider' // ModelName.relation (this relation must exist in your model)
        ]
    ];
    ...
    public function index() {
        ...
        Jqgrid::add_js_colmodel('foo_table', self::jqgrid_colmodel); // add colmodel columns to render in view
        return view('foo.index');
        ...
    }
    ...
    /**
     * Generate json response for jqgrid
     * @return string
     */
    public function datagrid() {
        Jqgrid::init('foo_table', self::jqgrid_colmodel, self::jqgrid_format());
        Jqgrid::get_query()->whereIn('user_id', auth()->user()->id); // add extra query conditions
        return Jqgrid::datagrid();
    }
    ...
    /**
     * Return closure function to format jqgrid columns (optional)
     * @return function
     */
    private static function jqgrid_format() {
        return function ($column, $value) {
            $result = $value;
            if ($column == 'name') {
                $result = ucfirst($value);
            }
            return $result;
        };
    }
}
```

In your view javascript
```javascript
// foo/index.blade.php
...
<!-- Load Jqgrid scripts in your scripts section -->
{!! Jqgrid::scripts() !!}
...
$('#jqgrid').jqGrid({
    url: '{{ url("foo/datagrid") }}',
    colModel: {!! Jqgrid::js_colmodel() !!}
    ...
});
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
