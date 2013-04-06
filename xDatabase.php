<?php
/**
 * класс базы данных проекта
 * Внешние зависиости
 *  ENGINE::debug, ::error, ::option, ::cache
 * <%=POINT::get('hat','comment');%>






 */

/**
 * общий наследник всех базоданческих драйверов
 */
class xDatabase_parent
{
    /**
     * для оперативной подмены в случае тестирования
     * @param $s
     * @return string
     */
    function escape($s){
        return  mysql_real_escape_string($s);
    }

    /** @var bool - флаг - нужно ли инициализироваться на старте. Устанавливать соединение и так далее... */
    protected $_init = true;

    /** @var int - счетчик выполненных запросов */
    protected $q_count = 0;

    /** @var null|resource - сопроводительна переменная - линк работы с открытой базой */
    protected $db_link = NULL;

    /** @var string -временная еременная для хранения ключа кэша*/
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
     * установить параметры. Параметры ставятся в виде строки слова через пробел.
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
        $rows = $this->fetch_row($result);
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
        while ($row = $this->fetch_row($result)) {
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
        $rows = $this->fetch_assoc($result);
        $this->free($result);
        return $this->cache($this->cachekey, $rows);
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
        while ($row = $this->fetch_assoc($result)) {
            $res[] = $row;
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
        if (is_resource($result))
            return mysql_fetch_assoc($result);
        else
            return array();
    }

    /******************************************************************************
     * fetch_row
     */
    public function fetch_row($result)
    {
        if (is_resource($result))
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
    public $debug = false;
    private $tpl = null;

    function __construct()
    {
        parent::__construct();
        $this->tpl = new sql_template();
        $this->tpl->regcns('prefix', ENGINE::option('database.prefix', 'xsite'));
        $this->tpl->regcns('CODE', ENGINE::option('database.code', 'UTF8'));
        if ($this->_init) {
            $this->query("SET NAMES '{{CODE}}'");
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
        $result = mysql_query($sql = call_user_func_array($func, $arg) /* , $this->db_link */);
        if (!$result) {
            ENGINE::error('Invalid query: ' . mysql_error() . "\n" .
                'Whole query: ' . $sql);
        } else {
            $this->q_count += 1;
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
    private $_debug = false;
    /** @var bool|Memcache */
    private $mcache = false;
    public $_cache = true;
    private $c_count=0;

    function cache($name, $data = false, $time = 28800)
    {
        if (!$this->_cache) return $data;
        if (false === $data) {
            if (FALSE !== ($result = $this->mcache->get($name))) {
                $this->c_count += 1;
                return unserialize($result);
            }
            return false;
        } else  {
            $this->mcache->set($name,serialize($data), MEMCACHE_COMPRESSED,$time);
        }
        return $data;
    }

    /**
     * вывод финального репорта про количество запросов.
     */
    function report($format="%s(%s) queries")
    {
        return sprintf($format, $this->q_count, $this->c_count);
    }

    function __construct($option = '')
    {
        parent::__construct($option);
        $this->prefix = ENGINE::option('database.prefix', 'xsite');
        if ($this->_init) {
            $this->query("SET NAMES '" . ENGINE::option('database.code', 'UTF8') . "'");
        }
        $this->mcache = new Memcache;
        $this->mcache->connect('localhost', 11211);
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
        if ($cached) {
            $this->cachekey = ENGINE::option('cache.prefix', 'xx') . md5($arg[0]);
            if (false !== ($result = $this->cache($this->cachekey)))
                return $result;
        }
        ;
        //ENGINE::debug( 222/* ,$this->db_link */);
        $result = mysql_query($sql = $this->_($arg) /* , $this->db_link */);
        if (!$result) {
            ENGINE::error('Invalid query: ' . mysql_error() . "\n" .
                'Whole query: ' . $sql);
        } else {
            $this->q_count += 1;
        }
        if ($this->_debug) {
            ENGINE::debug('QUERY: <pre>' . $sql . '</pre><hr/>');
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
     *  ?(...) - параметр - массив, для каждой кары ключ-значение массива
     *      применяется формат из скобок. Разделяются запятыи
     *
     * @example
     *    - ->_('insert into ?k (?(?k)) values (?2(?v))','x_table'
     *          ,array('one'=>1,'two'=>1))
     *    - ->_('update ?k set ?(?k=?) where `id`=?d','x_table'
     *          ,array('one'=>1,'two'=>1),5)
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
        while (preg_match('/(?<!\\\\)\?(\d*)(i|d|x|k|_|s|\(([^\)]+)\)|)/i'
            , $format, $m, PREG_OFFSET_CAPTURE, $start)
        ) {
            $x = '';
            $cur = $m[1][0];
            if (empty($cur)) $cur = $cnt++;
            if (empty($m[2][0])) {
                if (is_int($args[$cur]) || ctype_digit($args[$cur]))
                    $x = (0 + $args[$cur]);
                else
                    $x = '"' . $this->escape($args[$cur]) . '"';
            } else switch ($m[2][0]) {
                case '_':
                    if (!isset($pref))
                        $pref = ENGINE::option('database.prefix', 'xxx_');
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
                    $x = '`' . $this->escape($args[$cur]) . '`';
                    break;
                case 's':
                    $x = '"' . $this->escape($args[$cur]) . '"';
                    break;
                default: //()
                    if (is_array($args[$cur])) {
                        $s = array();
                        foreach ($args[$cur] as $k => $v)
                            $s[] = $this->_(array($m[3][0], $k, $v));
                        $x = implode(',', $s);
                    }
            }
            $format = substr($format, 0, $m[0][1]) . $x . substr($format, $m[2][1] + strlen($m[2][0]));
            $start = $m[0][1] + strlen($x);
        }
        return $format;
    }


}

