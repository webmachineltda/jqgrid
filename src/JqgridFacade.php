<?php
namespace Webmachine\Jqgrid;

use Illuminate\Support\Facades\Facade;

class JqgridFacade extends Facade {

    protected static function getFacadeAccessor() {
        return 'jqgrid';
    }
}