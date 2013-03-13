<?php
/**
 * класс-наследник шаблонизатора для использования в Юнит-тестах.
 */

class sql_child extends sql_template {

    public function __construct(){
        parent::__construct();
        parent::$escape = 'mysql_escape_string';
        $this->filters['somefilter']=array($this,'filter_somefilter');
        $this->filters['faked']=array($this,'filter_faked');
    }

    function filter_somefilter($s) {
        return '"nothing here!"' ;
    }

    function filter_faked($s) {
        return '"nothing here!' ; // return unquoted string, so create_function will fail
    }

}

/** а так они размножаюццо  */
//sql_template::$className='sql_child';