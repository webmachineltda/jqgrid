<?php
$default_format = function ($column, $value) {
    return $value;
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
    'default_relation_namespace' => 'App'
];