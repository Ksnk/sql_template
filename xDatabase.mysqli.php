<?php
/**
 * класс базы данных проекта. Вариант с mysqli драйвером
 * Внешние зависиости
 *  ENGINE::debug, ::error, ::option, ::cache
 * <%=POINT::get('hat','comment');%>






 */

/**
 * класс, собирающий длинный insertValues
 */
class dbInsertValues {

    /** @var string */
    private $sql_start='', $sql_finish='';

    /** @var xDatabaseLapsi */
    private $parentDb;

    /** @var array */
    private $result_values=array();

    /** @var int - длина результирующего запроса */
    private $result_length=0;

    /** @var int */
    private $max_result_length=32000;

    /**
     * @param $start
     * @param $finish
     */
    public function __construct($start,$finish,$parent) {
        $this->sql_start=$start;
        $this->sql_finish=$finish;
        $this->parentDb=$parent;
        $this->result_length=strlen($start)+strlen($finish);
    }

    /**
     * @param array $values
     */
    public function insert($values){
        $v=$this->parentDb->_(array('(?[?2])',$values));
        $this->result_length+=strlen($v)+1;
        if($this->result_length>$this->max_result_length){
            $this->flush();
        }
        $this->result_values[] = $v;
    }

    /**
     * выполнение запроса
     */
    public function flush(){
        if(count($this->result_values)>0){
            $this->parentDb->query($this->sql_start
                .implode(',',$this->result_values)
                .$this->sql_finish
            );
            $this->result_values=array();
            $this->result_length=strlen($this->sql_start)+strlen($this->sql_finish);
        }
    }

    function __destruct (){
        $this->flush();
    }
}

/**
 * класс, возвращаемый в ответ на длинный select
 */
class dbIterator implements Iterator {
    private $position = 0;
    private $dbresult =null;
    private $data=null;

    public function __construct($dbresult) {
        $this->dbresult=$dbresult ;
        $this->position = 0;
    }

    function rewind() {
        $this->data=mysql_fetch_assoc($this->dbresult);
        $this->position = 0;
    }

    function current() {
        return $this->data;
    }

    function key() {
        return $this->position;
    }

    function next() {
        $this->data=mysql_fetch_assoc($this->dbresult);
    }

    function valid() {
        return is_array($this->data);
    }

    public function __destruct() {
        if(is_resource($this->dbresult))
            mysql_free_result($this->dbresult);
    }

}


/**
 * общий наследник всех базоданческих драйверов
 */
class xDatabase_parent
{
    /** @var bool - флаг - нужно ли инициализироваться на старте. Устанавливать соединение и так далее... */
    protected $_init = true;

    /** @var bool - флаг - не исполнять запросы, а просто дебажиться.... */
    protected $_test = false;

    /** @var int - счетчик выполненных запросов */
    protected $q_count = 0;

    /** @var null|resource - сопроводительная переменная - линк работы с открытой базой */
    protected $db_link = NULL;

    /** @var string -временная переменная для хранения ключа кэша*/
    protected $cachekey = '';

    /**
     * вывод финального репорта про количество запросов.
     * @param string $format - формат вывода репорта. Вдруг пригодится.
     * @return string
     */
    function report($format="%s queries,")
    {
        return sprintf($format, $this->q_count);
    }

    /**
     * функция кэширования запроса по sql
     * @param $name
     * @param bool $data
     * @return bool
     */
    function cache($name, $data = false)
    {
        return $data;
    }

    /**
     * установить параметры. Параметры ставятся в виде строки со словами через пробел.
     * параметр - внутреняя переменная класса с именем `_параметр`
     * в природе бывают параметры
     * - init(*), noinit
     * - debug, nodebug(*)
     * - cache(*), nocache
     * @param $option
     */
    function set_option($option)
    {
        foreach (explode(' ', $option) as $o) {
            if (strpos($o, 'no') === 0) {
                $o = substr($o, 2);
                $val = false;
            } else
                $val = true;
            $o = '_' . $o;
            if (property_exists($this, $o))
                $this->$o = $val;
        }
    }

    /**
     * конструирование объекта
     * -- попишем инстанс,  непонтно зачем
     * -- инициализация коннекта
     */
    function __construct($option = '')
    {
        if (!empty($option))
            $this->set_option($option);
        if ($this->_init) {
            $this->db_link = mysql_connect(
                ENGINE::option('database.host'),
                ENGINE::option('database.user'),
                ENGINE::option('database.password')
            );
            if(empty($this->db_link))
                ENGINE::error('can\'t connect: ' .
                    ENGINE::option('database.host') . "\n" .
                    ENGINE::option('database.user'). "\n" .
                    ENGINE::option('database.password'));
            mysql_select_db(ENGINE::option('database.base') /* , $this->db_link */);
        }
    }

    /**
     * выбрать первое поле в результатах запроса. LIMIT 1 ДОЛЖЕН присутствовать.
     * @param $query
     * @return mixed
     */
    function selectCell($query)
    {
        $result = $this->_query(func_get_args(), true);
        if (!is_resource($result))
            return $result;
        $rows = mysql_fetch_row($result);
        $this->free($result);
        if (!$rows)
            return false;
        return $this->cache($this->cachekey, $rows[0]);
    }

    /**
     * выбрать первое поле в результатах запроса. LIMIT 1 РЕКОМЕНДУЕТСЯ.
     * @param $query
     * @return mixed
     */
    function selectCol($query)
    {
        $result = $this->_query(func_get_args(), true);
        if (!is_resource($result))
            return $result;
        $res = array();
        while ($row = mysql_fetch_row($result)) {
            $res[] = $row[0];
        }
        $this->free($result);
        return $this->cache($this->cachekey, $res);
    }

    /**
     * выбрать первую строку.
     * @param $query
     * @return mixed
     */
    function selectRow($query)
    {
        $result = $this->_query(func_get_args(), true);
        if (!is_resource($result))
            return $result;
        $rows = mysql_fetch_assoc($result);
        $this->free($result);
        return $this->cache($this->cachekey, $rows);
    }

    function selectAll($query){
        $result = $this->_query(func_get_args(), true);
        if (!is_resource($result))
            return $result;
        $res = array();
        while ($row = mysql_fetch_assoc($result)) {
            $res[] = $row;
        }
        $this->free($result);
        return $this->cache($this->cachekey, $res);
    }

    /**
     * Выбрать все, каждая строка - ассоциативный массив.
     */
    function select($query)
    {
        $result = $this->_query(func_get_args(), true);
        if (!is_resource($result))
            return $result;
        $res = array();
        while ($row = mysql_fetch_assoc($result)) {
            $res[] = $row;
        }
        $this->free($result);
        return $this->cache($this->cachekey, $res);
    }

    /**
     * Выбрать все из большого запроса, вернуть итератор.
     */
    function selectLong($query)
    {
        $result = $this->_query(func_get_args(), false); // не кэшировать большие запросы
        if (!is_resource($result))
            return $result;
        return new dbIterator($result);
    }
    /**
     * Выбрать все из запроса, вернуть массив с инлексами.
     */
    function selectByInd($idx,$query)
    {
        $result = $this->_query(func_get_args(), true);
        if (!is_resource($result))
            return $result;
        $res = array();
        while ($row = mysql_fetch_assoc($result)) {
            if(!empty($row[$idx]))
                $res[$row[$idx]] = $row;
        }
        $this->free($result);
        return $this->cache($this->cachekey, $res);
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
     * Не нужно, но чистота требует жертв
     */
    function __destruct()
    {
        if (!empty($this->db_link) && is_resource($this->db_link))
            mysql_close($this->db_link);
    }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * выполнить запрос с параметрами.
     * sql парсится и дополняется параметрами.
     * @param array $arg - Запрос + параметры запроса
     * @param string $options - опции запроса
     * @return resource
     */
    protected function _query($arg, $options = '')
    {
        ENGINE::error('unrealised awhile ');
        return null;
    }

    /**
     * выполнить дамп sql.
     */
    public function sql_dump($sql)
    {
        foreach (explode(";\n", str_replace("\r", '', $sql)) as $s) {
            $s = trim(preg_replace('~^\-\-.*?$|^#.*?$~m', '', $s));
            if (!empty($s))
                $this->query($s);
        }
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
 * x3 зачем он нужен
 * По иде - простой класс для работы с mysql без наворотов
 */
class xDatabase extends xDatabase_parent
{
    protected function _query($arg, $options = '')
    {
        return mysql_query($arg[0] /* , $this->db_link */);
    }
}

/**
 * вариант xDatabase для работы с mysql - Xilen style
 * Используется twig-like язык запросов.
 */
class xDatabaseXilen extends xDatabase_parent
{
    protected $_debug = false;
    private $tpl = null;

    function __construct()
    {
        parent::__construct();
        $this->tpl = new sql_template();
        $this->tpl->regcns('prefix', ENGINE::option('database.prefix', 'xsite'));
        $this->tpl->regcns('CODE', ENGINE::option('database.code', 'UTF8'));
        if ($this->_init) {
            $this->query("SET NAMES {{CODE}}");
        }
    }

    /**
     * выполнить запрос с параметрами.
     * sql парсится и дополняется параметрами.
     * @param array $arg - Запрос + параметры запроса
     * @param string $options - опции запроса
     * @return resource
     */
    protected function _query($arg, $options = '')
    {
        $func = $this->tpl->parse($arg[0]);
        $sql = call_user_func_array($func, $arg);
        if(!$this->_test) {
            $result = mysql_query($sql /* , $this->db_link */);
            if (!$result) {
                ENGINE::error('Invalid query: ' . mysql_error() . "\n" .
                    'Whole query: ' . $sql);
            } else {
                $this->q_count += 1;
            }
        } else {
            ENGINE::debug('TEST: ' . $sql);
            $result=false;
        }
        if ($this->_debug) {
            ENGINE::debug('QUERY: ' . $sql);
        }
        return $result;
    }
}

/**
 * вариант xDatabase для работы с mysql с memcache-кэшированием (LAPSI style)
 */
class xDatabaseLapsi extends xDatabase_parent
{
    protected $_debug = false;
    protected $_once = false;
    /** @var bool|Memcache */
   // private $mcache = false;
    public $_cache = true;
    private $c_count=0;

    function cache($name, $data = false, $time = 28800)
    {
        if (!$this->_cache) return $data;
        if (false === $data) {
            if (FALSE !== ($result = ENGINE::cache($name))) {
                $this->c_count += 1;
                return unserialize($result);
            }
            return false;
        } else  {
            ENGINE::cache($name,serialize($data), $time);
        }
        return $data;
    }

    /**
     * вывод финального репорта про количество запросов.
     */
    function report($format="mysql:[%s(%s) queries] ")
    {
        return sprintf($format, $this->q_count, $this->c_count);
    }

    function __construct($option = '')
    {
        parent::__construct($option);
        $this->prefix = ENGINE::option('database.prefix', 'xsite');
        if( ENGINE::option('database.debug')){
           $this->_debug=true;
        }

        if ($this->_init) {
            $this->query("SET NAMES " . ENGINE::option('database.code', 'UTF8') . ";");
        }
    }

    /**
     * Вставить. Сгенерировать очень длинную простыню.
     */
    function insertValues($sql)
    {
        $sql = $this->_(func_get_args());
        list($start,$finish)=explode('()',$sql);
        return new dbInsertValues($start,$finish,$this);
    }

    /**
     * выполнить запрос с параметрами.
     * sql парсится и дополняется параметрами.
     * @param array $arg - Запрос + параметры запроса
     * @param bool $cached
     * @internal param string $options - опции запроса
     * @return resource
     */
    protected function _query($arg, $cached = false)
    {
        if ($this->_debug) {
            $start=microtime(true);
        }
        if(1===$this->_once){
            $this->_once=false;
        }if(true===$this->_once){
            $this->_once=1;
        }
        $sql = $this->_($arg);
        if ($cached) {
            $this->cachekey = ENGINE::option('cache.prefix', 'x') . md5($sql);
            if (false !== ($result = $this->cache($this->cachekey))){
                if ($this->_debug) {
                    ENGINE::debug('QUERY(cache)'.sprintf('[%f]'
                        ,microtime(true)-$start).': ' . $sql . "\n",'~function|_query','~shift|1');
                }
                return $result;
            }
        }
        //ENGINE::debug( 222/* ,$this->db_link */);
        if(!$this->_test){
            $result = mysql_query($sql /* , $this->db_link */);
            if (!$result) {
                ENGINE::error('Invalid query: ' . mysql_error() . "\n" .
                    'Whole query: ' . $sql);
            } else {
                $this->q_count += 1;
            }
        } else {
            ENGINE::debug("QUERY-TEST:\n" . $sql . "\n",'~function|_query','~shift|1');
            $result=false;
        }
        if ($this->_debug) {
            ENGINE::debug('QUERY'.sprintf('[%f]'
                ,microtime(true)-$start).":\n" . $sql . "\n",'~function|_query','~shift|1');
        }

        return $result;
    }

    /**
     * helper-заполнятель sql конструкций.
     * список подстановок
     *  ?_ - подставить префикс таблицы, указатель парамемтров не перемещается
     *  ?12x - подставить 12 по счету параметр. Указатель параметров не перемещается
     *      без номера - указатель перемещается на следующий параметр
     *  ?x - подставить параметр без обработки
     *  ?d, ?i - параметр - чиcло. Явно приводится к числовому значению, каычек нет.
     *  ?k - параметр - имя поля, обрамляется `` кавычками
     *  ?s - параметр - строка - выводится в двойных кавычках,
     *      делается mysql_real_escape_string
     *  ? - анализируется значение, для чисел не вставляются кавычки,
     *      для строк делается ескейп
     *  ?[...] - параметр - массив, для каждой пары ключ-значение массива
     *      применяется формат из скобок. Разделяются запятыми
     *
     * @example 
     * простой insert
     *    - $db->_(array('insert into ?k (?(?k)) values (?2(?2))','x_table'
     *           ,array('one'=>1,'two'=>2,'three'=>'облом'))) 
     *     ==> insert into `x_table` (`one`,`two`,`three`) values (1,2,"облом")
     *  
     * insert on duplicate key
     *    - $db->_(array('insert into ?k (?(?k)) values (?2(?2))
     *      on duplicate key set ?2(?k=?)','x_table'
     *      ,array('one'=>1,'two'=>2,'three'=>'облом')))
     *     ==> insert into `x_table` (`one`,`two`,`three`) values (1,2,"облом")
     *      on duplicate key set `one`=1,`two`=2,`three`="облом"
     *  - $db->query(
     *      'insert into `laptv_video` (`LASTUPDATE`,?[?k]) values (NOW(),?1[?2])'.
     *      'on duplicate key update  `LASTUPDATE`=NOW(),?1[?1k=VALUES(?1k)];'
     *      ,$data);
     *
     * генерация простыни
     *   - $x=array(
     *         array('x'=>1,'y'=>2,'z'=>3),
     *         array('x'=>1,'y'=>2,'z'=>3),
     *         array('x'=>1,'y'=>2,'z'=>3),
     *       ...
     *    )
     *    $part=array();
     *    foreach($x as $xx) $part[]=...->_(array(array('(?(?2))',$xx)));
     *    ->_(array('insert into ?k (?(?k)) values ?3(?2x);','table',$x[0],$part)))
     * @param array $args  - нулевой параметр - формат
     * @return string
     */
    function _($args)
    {
        static $pref;
        //$args=func_get_args();
        $format = $args[0];
        $cnt = 1;
        $start = 0;
        while (preg_match('/(?<!\\\\)\?(\d*)([id\#ayxk_s]|\[([^\]]+)\]|)/i'
            , $format, $m, PREG_OFFSET_CAPTURE, $start)
        ) {
            $x = '';
            $cur = $m[1][0];
            if (empty($cur)) $cur = $cnt++;
            if (empty($m[2][0])) {
                if(''===$args[$cur])
                    $x = '""';
                elseif(0===$args[$cur])
                    $x = 0;
                elseif('0'===$args[$cur])
                    $x = 0;
                elseif (empty($args[$cur]))
                    $x = 'NULL';
                elseif (is_int($args[$cur]) || ctype_digit($args[$cur]))
                    $x = (0 + $args[$cur]);
                else
                    $x = '"' . mysql_real_escape_string($args[$cur]) . '"';
                $xx='';
            } else switch ($xx=$m[2][0]) {
                case '_':
                    if (!isset($pref))
                        $pref = ENGINE::option('database.prefix', 'xxx_');
                    if(empty($m[1][0]))$cnt--;
                    $x = $pref;
                    break;
                case 'i':
                case 'd':
                    $x = (0 + $args[$cur]);
                    break;
                case 'x':
                    $x = $args[$cur];
                    break;
                case 'k':
                    $x = '`' . str_replace("`","``",$args[$cur]) . '`';
                    break;
                case 's':
                    $x = '"' . mysql_real_escape_string($args[$cur]) . '"';
                    break;
                case 'y':
                    $x = mysql_real_escape_string($args[$cur]);
                    break;
                default: //()
                    $explode=',';
                    if($xx=='a'){ // ?a
                        reset($args[$cur]);
                        if(key($args[$cur]))
                            $tpl='?k=?';
                        else
                            $tpl='?2';
                    } else if($xx=='#'){ //?#
                        $tpl='?2k';
                    } else {  // массив в параметрах
                        $tpl=$m[3][0];//if(!empty($m[4][0]))$tpl.=$m[4][0];
                        if(false===($pos=strpos($tpl,'|'))){
                            $explode=', ';
                        } else {
                            $explode=substr($tpl,$pos+1);
                            $tpl=substr($tpl,0,$pos);
                        }
                    }
                    if (is_array($args[$cur])) {
                        if(empty($args[$cur]))
                            return 'NULL';
                        $s = array();
                        foreach ($args[$cur] as $k => $v)
                            $s[] = $this->_(array($tpl, $k, $v));
                        $x = implode($explode, $s);
                    }
            }
            $format = substr($format, 0, $m[0][1]) . $x . substr($format, $m[2][1] + strlen($xx));
            $start = $m[0][1] + strlen($x);
        }
        return $format;
    }


}

