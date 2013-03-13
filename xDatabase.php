<?php
/**
 * класс базы данных проекта
 * <%=POINT::get('hat','comment');%>






 */

class xDatabase_parent
{

    private static $instance = null;

    public $q_count = 0;
    public $c_count = 0;

    protected $db_link;

    /** @var string */
    protected $cachekey='';

    /**
     * вывод финального репорта про количество запросов.
     */
    function report(){
        return sprintf("%s(%s) queries,", $this->q_count, $this->c_count);
    }

    function cache($name,$data=false){
        return $data;
    }

    /**
     * конструирование объекта
     * -- попишем инстанс,  непонтно зачем
     * -- инициализация коннекта
     */
    function __construct()
    {
        self::$instance = $this;
        $this->db_link = mysql_connect(
            ENGINE::option('database.host'),
            ENGINE::option('database.user'),
            ENGINE::option('database.password')
        );
        mysql_select_db(ENGINE::option('database.base'), $this->db_link);
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
        $result = $this->_query(func_get_args(),true);
        if(!is_resource($result))
            return $result;
        $rows = $this->fetch_row($result);
        $this->free($result);
        if(!$rows)
            return false;
        return $this->cache($this->cachekey,$rows[0]);
    }

    /**
     * выбрать первое поле в результатах запроса. LIMIT 1 ДОЛЖЕН присутствовать.
     * @param $query
     * @return mixed
     */
    function selectCol($query)
    {
        $result = $this->_query(func_get_args(),true);
        if(!is_resource($result))
            return $result;
        $res=array();
        while ($row = $this->fetch_row($result)) {
            $res[]=$row[0];
        }
        $this->free($result);
        return $this->cache($this->cachekey,$res);
    }

    /**
     * выбрать первую строку.
     * @param $query
     * @return mixed
     */
    function selectRow($query)
    {
        $result = $this->_query(func_get_args(),true);
        if(!is_resource($result))
            return $result;
        $rows = $this->fetch_assoc($result);
        $this->free($result);
        return $this->cache($this->cachekey,$rows);
    }

    /**
     * Выбрать все, каждая строка - ассоциативный массив.
     */
    function select($query)
    {
        $result = $this->_query(func_get_args(),true);
        if(!is_resource($result))
            return $result;
        $res=array();
        while ($row = $this->fetch_assoc($result)) {
            $res[]=$row;
        }
        $this->free($result);
        return $this->cache($this->cachekey,$res);
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
     * Вставить. Вернуть последний вставленный индекс.
     */
    function delete($query)
    {
        $result = $this->_query(func_get_args());
        $this->free($result);
        return @mysql_affected_rows($this->db_link);
    }

    function update($query)
    {
        $result = $this->_query(func_get_args());
        $this->free($result);
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
        ENGINE::error('unrealised awhile ');
        return null;
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

/******************************************************************************
 * fetch_assoc
 */
    public function fetch_assoc($result)
    {
        if(is_resource($result))
            return mysql_fetch_assoc($result);
        else
            return array();
    }

/******************************************************************************
 * fetch_row
 */
    public function fetch_row($result)
    {
        if(is_resource($result))
            return mysql_fetch_row($result);
        else
            return array();
    }

/******************************************************************************
 * free
 */
    function free($handle)
    {
        if (is_resource($handle))
            mysql_free_result($handle);
    }
}

/**
 * вариант xDatabase для работы с mysql - Xilen style
 */
class xDatabaseXilen extends xDatabase_parent
{
    private $debug= false;
    private $tpl=null;

    function __construct(){
        parent::__construct();
        $this->tpl=new sql_template();
        $this->tpl->regcns('prefix', ENGINE::option('database.prefix', 'xsite'));
        $this->tpl->regcns('CODE', ENGINE::option('database.code', 'UTF8'));
        $this->query("SET NAMES '{{CODE}}'");
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
        $func = $this->tpl->parse($arg[0]);
        $result = mysql_query($sql=call_user_func_array($func, $arg), $this->db_link);
        if (!$result) {
            ENGINE::error('Invalid query: ' . mysql_error() . "\n" .
                'Whole query: ' . $sql);
        } else {
            $this->q_count += 1;
        }
        if ($this->debug) {
            ENGINE::debug('QUERY: ' . $sql );
        }
        return $result;
    }
}

/**
 * вариант xDatabase для работы с mysql с memcache-кэшированием (LAPSI style)
 */
class xDatabaseLapsi extends xDatabase_parent
{
    public $debug= false;
    /** @var bool|Memcache */
    private $mcache=false;
    public $cache_allowed = true;

    function cache($name,$data=false){
        if(!$this->cache_allowed) return $data;
        if(false===$data){
            //return false ;//
            if(FALSE!==($result=$this->mcache->get($name))){
                $this->c_count += 1;
                return unserialize($result);
            }
            return false;
        } else
            $this->mcache->set($name,serialize($data), MEMCACHE_COMPRESSED,5*60);
        return $data;
    }

    function __construct(){
        parent::__construct();
        $this->prefix= ENGINE::option('database.prefix', 'xsite');
        $this->query("SET NAMES '".ENGINE::option('database.code', 'UTF8')."'");
        $this->mcache= new Memcache;
        $this->mcache->connect('localhost', 11211) ;
    }

    /**
     * выполнить запрос с параметрами.
     * sql парсится и дополняется параметрами.
     * @param array $arg - Запрос + параметры запроса
     * @param bool $cached
     * @internal param string $options - опции запроса
     * @return resource
     */
    protected function _query($arg , $cached = false)
    {
        if($cached){
            $this->cachekey ='x'.md5($arg[0]);
            if(false!==($result=$this->cache($this->cachekey)))
                return $result;
        };
        $result = mysql_query($sql=$arg[0], $this->db_link);
        if (!$result) {
            ENGINE::error('Invalid query: ' . mysql_error() . "\n" .
                'Whole query: ' . $sql);
        } else {
            $this->q_count += 1;
        }
        if ($this->debug) {
            ENGINE::debug('QUERY: <pre>' . $sql . '</pre><hr/>');
        }

        return $result;
    }
}