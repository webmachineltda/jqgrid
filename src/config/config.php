<?php
$default_format = function ($column, $value, $row) {
    return $value;
};

$default_filter_format = function ($field, $data) {
    return $data;
};

$default_add_js_colmodel_callback = function ($colmodel, $table) {
    return $colmodel;
};

$default_js_colmodel_callback = function ($colmodel, $table) {
    return json_encode($colmodel);
};

return [
    'scripts' => [
        // add jqgrid scripts
    ],
    'defaults' => [ // @see http://www.trirand.com/jqgridwiki/doku.php?id=wiki:options
        'datatype' => 'json',
        'rowNum' => 10,
        'rowList' => [10, 20, 30],
        'sortname' => 'id',
        'sortorder' => 'desc',
        'height' => 'auto',
        'autowidth' => true,
        'toolbarfilter' => true,
        'viewrecords' => true
    ],
    'default_format' => $default_format,
    'default_filter_format' => $default_filter_format,
    'default_add_js_colmodel_callback' => $default_add_js_colmodel_callback,
    'default_js_colmodel_callback' => $default_js_colmodel_callback,
    'default_relation_namespace' => 'App'
];