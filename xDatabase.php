<?php

class xDatabase extends sql_template
{

    private static $instance = null;

    public $q_count = 0;
    private $debug= false;

    protected $db_link;

    /**
     * конструирование объекта
     * -- попишем инстанс,  непонтно зачем
     * -- инициализация коннекта
     */
    function __construct()
    {
        parent::__construct();
        self::$instance = $this;
        $this->db_link = mysql_connect(
            ENGINE::option('database.host'),
            ENGINE::option('database.user'),
            ENGINE::option('database.password')
        );
        $this->regcns('prefix', ENGINE::option('database.prefix', 'xsite'));
        $this->regcns('CODE', ENGINE::option('database.code', 'UTF8'));
        mysql_select_db(ENGINE::option('database.base'), $this->db_link);

        $this->query("SET NAMES '{{CODE}}'");
    }

    function get_request_count(){
        return $this->q_count;
    }

    /**
     * выбрать первое поле в результатах запроса. LIMIT 1 ДОЛЖЕН присутствовать.
     * @param $query
     * @return mixed
     */
    function selectCell($query)
    {
        $result = $this->_query(func_get_args());
        $rows = $this->fetch_row($result);
        $this->free($result);
        return $rows[0];
    }

    /**
     * выбрать первую строку.
     * @param $query
     * @return mixed
     */
    function selectRow($query)
    {
        $result = $this->_query(func_get_args());
        $rows = $this->fetch_assoc($result);
        $this->free($result);
        return $rows;
    }

    /**
     * Выбрать все, каждая строка - ассоциативный массив.
     */
    function select($query)
    {
        $result = $this->_query(func_get_args());
        $res=array();
        while ($row = $this->fetch_assoc($result)) {
            $res[]=$row;
        }
        $this->free($result);
        return $res;
    }

    /**
     * Вставить. Вернуть последний вставленный индекс.
     */
    function insert($query)
    {
        $result = $this->_query(func_get_args());
        $this->free($result);
        return @mysql_insert_id($this->db_link);
    }

    /**
     * выполнить запрос не возвращая результата.
     * @param $query
     * @return mixed
     */
    function query($query)
    {
        $result = $this->_query(func_get_args());
        $this->free($result);
    }

    /**
     * закрыть неправеднооткрытое.
     * Ненужно, но чистота требует жертв
     */
    function __destruct()
    {
        if(is_resource($this->db_link))
            mysql_close($this->db_link);
    }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * выполнить запрос с параметрами.
     * sql парсится и дополняется параметрами.
     * @param array $arg - Запрос + параметры запроса
     * @param string $options - опции запроса
     * @return resource
     */
    protected function _query($arg , $options = '')
    {
        $func = $this->parse($arg[0]);

        $result = mysql_query(call_user_func_array($func, $arg), $this->db_link);
        if (!$result) {
            ENGINE::error('Invalid query: ' . mysql_error() . "\n" .
                'Whole query: ' . call_user_func_array($func, $arg));
        } else {
            $this->q_count += 1;
        }
        if ($this->debug) {
            ENGINE::debug('QUERY: <pre>' . call_user_func_array($func, $arg) . '</pre><hr/>');
        }
        return $result;
    }

    /**
     * выполнить дамп sql.
     */
    public function sql_dump($sql){
        foreach (explode(";\n", str_replace("\r", '', $sql)) as $s) {
            $s = trim(preg_replace('~^\-\-.*?$|^#.*?$~m', '', $s));
            if (!empty($s))
                $this->query($s);
        }
    }

/******************************************************************************
 * mysql специфика
 */
    public function num_rows($result)
    {
        return (int)mysql_num_rows($result);
    }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function fetch_assoc($result)
    {
        if(is_resource($result))
            return mysql_fetch_assoc($result);
        else
            return array();
    }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function fetch_row($result)
    {
        if(is_resource($result))
            return mysql_fetch_row($result);
        else
            return array();
    }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function affected_rows()
    {
        return mysql_affected_rows($this->db_link);
    }


    function free($handle)
    {
        if (is_resource($handle))
            mysql_free_result($handle);
    }
}
