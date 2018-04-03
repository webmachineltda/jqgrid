<?php
namespace Webmachine\Jqgrid;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webmachine\CustomFields\CustomFieldsFacade as CustomFields;

/**
 * Jqgrid
 * 
 * Esta clase maneja las columnas y obtención de datos para jqgrid
 */
class Jqgrid {

    /**
     * Table
     * 
     * @var string
     * @access private
     */
    private $table;

    /**
     * Columnas para jqgrid
     * además de los parametros permitidos por ColModel API, es posible pasar un parametro relation:
     * 'relation' => 'Model1.relation1' o 'relation' => 'Model1.relation1>Model2.relation2'
     * 
     * @see http://www.trirand.com/jqgridwiki/doku.php?id=wiki:colmodel_options ColModel API.
     * @var array
     * @access private
     */    
    private $colmodel;
    
    /**
     * Js_colmodels
     * 
     * @var array 
     */
    private $js_colmodels;
    
    /**
     * Columns
     * 
     * @var array
     * @access private 
     */
    private $columns;
    
    /**
     * Format
     * 
     * @var callable
     * @access private
     */
    private $format;
    
    /**
     * Query
     * 
     * @var Illuminate\Database\Query\Builder
     * @access private 
     */
    private $query;
    
    /**
     * Use_relations
     * 
     * @var boolean
     * @acces private 
     */
    private $use_relations;
    
    /**
     * With_trash
     * 
     * @var boolean
     * @acces private 
     */
    private $with_trash;
    
    /**
     * Filter Format
     * 
     * @var callable
     * @acces private 
     */
    private $filter_format;     

    /**
     * Inicializa
     * 
     * @param string $table
     * @param array $colmodel
     * @param callable $format
     * @param boolean $use_relations
     * @param boolean $with_trash
     * @access public
     * @return void
     */
    public function init($table, $colmodel, $format = FALSE, $filter_format = FALSE, $use_relations = TRUE, $with_trash = FALSE) {
        $this->table = $table;
        $this->query = DB::table($this->table);
        $this->colmodel = $this->set_custom_fields($table, $colmodel);
        $this->columns = $this->get_columns();
        $this->query->select($this->columns);
        $this->format = $format == FALSE? config('jqgrid.default_format') : $format;
        $this->filter_format = $filter_format == FALSE? config('jqgrid.default_filter_format') : $filter_format;
        $this->use_relations = $use_relations;
        $this->with_trash = $with_trash;
        if(!$this->with_trash && Schema::hasColumn($this->table, 'deleted_at')) $this->query->whereNull($this->table . '.deleted_at');
        $this->set_relation_joins(); // crea joins a partir de relaciones en colmodel
    }
    
    /**
     * Obtiene datos de modelo en formato json apto para grilla jqgrid
     * 
     * @access public
     * @param bool $query_debug
     * @return string datos en formato json para grilla
     */    
    public function datagrid($query_debug = FALSE) {
        $page = request('page'); // la página requerida
        $limit = request('rows'); // cuantas filas queremos tener en la grilla
        $sidx = request('sidx'); // obtener la fila indice, es decir la que el usuario clickeó para ordenar
        $sord = request('sord'); // obtener la dirección (asc o desc)

        // renombra key búsqueda
        $sidx = !$sidx? $this->get_columns('%s.%s')[0] : $this->get_column_name($sidx);       
        
        //filtros para busqueda multiple
        $filters = json_decode(request('filters', ''), true);
        $filters_rules = $filters['rules'];
        $search = request('_search');
        
        //busqueda sin filtros multiples
        if($search == 'true' and empty($filters_rules)) {
            $field = request('searchField');
            $data = call_user_func($this->filter_format, $field, request('searchString'));
            $op = request('searchOper');            
            $this->set_where_rule($field, $op, $data);
        } else if ($search == 'true' and !empty($filters_rules)) { // Busqueda con filtros multiples
            foreach ($filters_rules as $filter_rule) {
                extract($filter_rule);
                $data = call_user_func($this->filter_format, $field, $data);
                $this->set_where_rule($field, $op, $data);
            }
        }

        $count = $this->query->count();

        if ($count > 0) {
            $total_pages = ceil($count / $limit);
        } else {
            $total_pages = 0;
        }
        if ($page > $total_pages)
            $page = $total_pages;
        $start = $limit * $page - $limit; // no poner $limit*($page - 1)
        
        if($query_debug) DB::enableQueryLog();
        $result = $this->query->orderBy($sidx, $sord)->skip($start)->take($limit)->get();

        $response['page'] = $page;
        $response['total'] = $total_pages;
        $response['records'] = $count;
        $response['rows'] = $this->set_response_rows($result);

        return $query_debug? response()->json(DB::getQueryLog()) : response()->json($response);
    }

    /**
     * Agrega campos personalizados
     * 
     * @param string $table
     * @param array $colmodel
     * @param boolean $set_joins indica si se deben agregar joins
     * @access private
     * @return array colmodel con campos personalizados
     */
    private function set_custom_fields($table = FALSE, $colmodel = FALSE, $set_joins = TRUE) {
        $custom_fields = CustomFields::getCustomFields($table);
        $n = 1;
        foreach ($custom_fields as $custom_field) {
            $alias = "V$n";
            $n++;
            $colmodel_item = ['label' => $custom_field->name, 'name' => "$alias.value"];
            array_splice($colmodel, $custom_field->order - 1, 0, [$colmodel_item]);
            // join
            if (!$set_joins) continue;
            $this->query->leftJoin("custom_field_values AS $alias", function ($join) use ($custom_field, $alias) {
                $join->on($custom_field->table . '.id', '=', "$alias.record_id")->where("$alias.custom_field_id", '=', $custom_field->id);
            });
        }
        return $colmodel;
    }    
    
    /**
     * Genera joins a partir de relaciones de un modelo
     * 
     * @access private
     * @return void
     */    
    private function set_relation_joins() {
        if(!$this->use_relations) return;
        $relations = [];
        foreach ($this->colmodel as $i => $col) {
            if (!isset($col['relation']))
                continue;
            foreach (explode('>', $col['relation']) as $relation) {
                if (!str_contains($relation, '.'))
                    continue;
                list($model_name, $relation_name) = explode('.', $relation);
                if ($relation_name != '' && !in_array($relation, $relations)) {
                    $this->set_relation_join($model_name, $relation_name);
                    $relations[] = $relation;
                }
            }
        }
    }

    /**
     * Genera join a partir de relacion de un modelo
     * 
     * @param string $model_name el nombre del modelo
     * @param string $relation_name el nombre de la relación
     * @access private
     * @return void
     */
    private function set_relation_join($model_name, $relation_name) {
        if(!str_contains($model_name, "\\")) {
            $model_name = config('jqgrid.default_relation_namespace') . "\\" . $model_name;
        }
        $model = new $model_name;
        $relation = $model->$relation_name();
        $table = $relation->getRelated()->getTable();
        $key = strpos(app()->version(), '5.4') !== FALSE ? $relation->getOwnerKey() : $relation->getOtherKey();
        $one = "$table.$key";
        $two = $relation->getQualifiedForeignKey();
        
        // relación directa con tabla padre intenta hacer join consigo mismo
        if($table == $this->table) {
            $table .= " AS $relation_name";
            $one = "$relation_name.$key";
        }
        
        $this->query->leftJoin($table, $one, '=', $two);
    }

    /**
     * Crea sentencia where de acuerdo a condiciones entregadas por la grilla
     * 
     * ['eq','ne','lt','le','gt','ge','bw','bn','in','ni','ew','en','cn','nc']
     * ['equal','not equal', 'less', 'less or equal','greater','greater or equal', 'begins with','does not begin with','is in','is not in','ends with','does not end with','contains','does not contain']
     * 
     * @param string $field el nombre del campo
     * @param string $op el operador
     * @param string $data el dato de búsqueda
     * @access private
     * @return void
     */
    private function set_where_rule($field, $op, $data) {
        $operator = '';
        $value = '';
        
        if($op == 'eq') {
            $operator = '=';
            $value = $data;
        } else if($op == 'ne') {
            $operator = '!=';
            $value = $data;
        } else if($op == 'lt') {
            $operator = '<';
            $value = $data;
        } else if($op == 'le') {
            $operator = '<=';
            $value = $data;
        } else if($op == 'gt') {
            $operator = '>';
            $value = $data;
        } else if($op == 'ge') {
            $operator = '>=';
            $value = $data;
        } else if($op == 'bw') {
            $operator = 'LIKE';
            $value = "$data%";
        } else if($op == 'bn') {
            $operator = 'NOT LIKE';
            $value = "$data%";
        } else if($op == 'in') {
            $operator = 'IN';
            $value = $data;
        } else if($op == 'ni') {
            $operator = 'NOT IN';
            $value = $data;
        } else if($op == 'ew') {
            $operator = 'LIKE';
            $value = "%$data";
        } else if($op == 'en') {
            $operator = 'NOT LIKE';
            $value = "%$data";
        } else if($op == 'cn') {
            $operator = 'LIKE';
            $value = "%$data%";
        } else if($op == 'nc') {
            $operator = 'NOT LIKE';
            $value = "%$data%";
        } else if($op == 'btw') {
            $this->query->whereBetween($this->get_column_name($field), $data);
            return;
        }
        $this->query->where($this->get_column_name($field), $operator, $value);
    }
        
    /**
     * Setea las filas de la respuesta a aprtir de los resultados de datos
     * 
     * @param Collection $result los resultados de datos
     * @access private
     * @return array las filas
     */
    private function set_response_rows($result) {        
        $rows = [];
        foreach ($result as $r) {
            $cell = [];
            foreach ($r as $column => $value) {
                    $cell[] = call_user_func($this->format, $column, $value, $r);
            }

            $rows[] = [
                'id' => $r->id,
                'cell' => $cell
            ];
        }
        return $rows;
    }
    
    /**
     * Obtiene nombre de columnas para metodo select
     * 
     * @param string $format1 formato para campos relacionados
     * @param string $format2 formato para campos tabla principal
     * @access private
     * @return array las columnas
     */
    private function get_columns($format1 = '%1$s.%2$s AS %1$s_%2$s', $format2 = '%s.%s') {
        $columns = [];
        foreach ($this->colmodel as $col) {
            $columns[] = $this->get_column_name($col['name'], $format1, $format2);
        }
        return $columns;      
    }
    
    /**
     * Transforma el nombre de la columna a formato especificado
     * 
     * @param string $field el nombre del campo
     * @param string $format1 formato para campos relacionados
     * @param string $format2 formato para campos tabla principal
     * @access private
     * @return string el nombre de la columna formateado
     */
    private function get_column_name($field, $format1 = '%s.%s', $format2 = '%s.%s') {
        $column = '';
        if (str_contains($field, '.')) {
            list($table_name, $column_name) = explode('.', $field);
            $column = sprintf($format1, $table_name, $column_name);
        } else {
            $column = sprintf($format2, $this->table, $field);
        }
        return $column;
    }
    
    /**
     * Elimina llave relations en arreglo colmodel
     * 
     * @param array $colmodel
     * @access private
     * @return array $colmodel sin relations
     */
    private function clean_relations($colmodel) {
        foreach ($colmodel as $i => $col) {
            if (isset($col['relation'])) unset($colmodel[$i]['relation']);
        }
        return $colmodel;
    }    
    
    /**
     * Obtiene query
     * 
     * @access public
     * @return Illuminate\Database\Query\Builder
     */
    public function get_query() {
        return $this->query;
    }    

    /**
     * Genera colnames en formato json
     * 
     * @access public
     * @return string colnames en formato json
     */
    public function js_colnames() {
        $labels = [];
        foreach ($this->colmodel as $col) {
            $labels[] = $col['label'];
        }
        return json_encode($labels);
    }

    /**
     * Agrega colmodel para vista
     * 
     * @param string $table
     * @param array $colmodel
     * @access public
     * @return void
     */
    public function add_js_colmodel($table, $colmodel) {    
        $colmodel = $this->clean_relations($colmodel);
        $colmodel = $this->set_custom_fields($table, $colmodel, FALSE);
        $this->js_colmodels[$table] = $colmodel;
    }    
    
    /**
     * Convierte colmodel desde array a json
     * 
     * @param $table
     * @access public
     * @return string colmodel en formato json
     */
    public function js_colmodel($table = FALSE) {
        if ($table === FALSE && !empty($this->js_colmodels)) $table = array_keys($this->js_colmodels)[0]; // si no especifico $table, obtiene la primera.
        $colmodel = isset($this->js_colmodels[$table])? $this->js_colmodels[$table] : [];
        return json_encode($colmodel);
    }
    
    /**
     * Imprime scripts necesarios para jqgrid, los obtiene desde config
     * 
     * @param array $scripts scripts adicionales
     * @return string los scripts
     */
    public function scripts($scripts = []) {
        $result = '';
        foreach(array_merge(config('jqgrid.scripts'), $scripts) as $script) {
            $result .= sprintf('<script src="%s"></script>', asset($script)) . "\n";
        }
        // obtengo defaults
        $defaults = config('jqgrid.defaults');
        if(!empty($defaults)) {
            $result .= "<script type='text/javascript'>\n";
            foreach($defaults as $key => $default) {
                $value = !is_array($default) && strpos($default, 'function') === 0? $default : json_encode($default);
                $result .= sprintf('$.jgrid.defaults.%s = %s;', $key, $value) . "\n";
            }
            $result .= "</script>";
        }
        
        return $result;
    }

}
