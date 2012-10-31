<?php
/**
 * попытка проверить "наследуемость" синглтона
 */

class sql_child extends sql_template {

    protected function __construct(){
        parent::__construct();
        $this->filters['somefilter']=array($this,'filter_somefilter');
        $this->filters['faked']=array($this,'filter_faked');
    }

    function filter_somefilter($s) {
        return '"nothing here!"' ;
    }

    function filter_faked($s) {
        return '"nothing here!' ; // return unquoted string, so cerate_function will fail
    }

}

/** а так они размножаюццо  */
sql_template::$className='sql_child';