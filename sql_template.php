<?php
/**
 * Задачей класса является трансляция строки SQL в код функции для create_function.
 * Код обязан получать параметры, как обычно
 * ->query(sql,$one,$two,$three)
 * получится
 * $func= sql_template::parce(sql);
 * mysql_query(call_user_func_array($func,$one,$two,$three))
 * по дороге доступно кэширование
 */

/**
 * класс служит для парсинга и для импорта новых фильтров в шаблон sql
 *
 *
 * 30.10.2012
 * so spent 0.644670 sec for sql, 0.467067 sec for pdo
 * so spent 0.340801 sec for sql, 0.354356 sec for pdo

 */
class sql_template
{

    static $escape = 'mysql_escape_string' ;// TODO : только на время тестирования
    /**
     * внутренний кэш класса. Служит для простенькой оптимизации при повторном выполнении запросов
     * @var array
     */
    private static $cache = array();

    /**
     * внутреняя переменная механизма трансляции
     * @var int
     */
    private $current_arg_number = 1;
    /**
     * для аккуратного обслешивания строки нужно сначала менять на плейсхолдеры, затем менять обратно
     * @var array
     */
    private $placeholders = array();

    /**
     * внутренний флаг фильтра - нужно вставлять real_escape или уже не нужно
     * @var
     */
    protected $noescape;

    /**
     * массиив фильтров
     * @var array
     */
    protected $filters;
    protected $filtersReg;

    /**
     * массиив переменных
     * @var array
     */
    protected $variables;

    /**
     * функция выдчи ошибки - недоразвита и, вероятно, не нужна.
     * @param $msg
     */
    protected function error($msg)
    {
        echo $msg;
    }

    static function escape($s){
        return call_user_func(self::$escape,$s);
    }

    /**
     * заполнение фильтров
     */
    public function __construct()
    {
        $this->filters = array(
            'noescape' => array($this, 'filter_noescape'),
            'keys' => 'array_keys(%s)',
            'values' => 'array_values(%s)',
            'int' => array($this, 'filter_int'),
            'float' => array($this, 'filter_float'),
            'pair' => array($this, 'filter_pair'),
        );
        $this->filtersReg = array(
            '/^join$/' => array($this, 'filter_join'),
            '/^join\s*\(([^\)]+)\)$/' => array($this, 'filter_join'),
        );
    }

    /**
     * возможность стороннего экспорта фильтров
     * @param $key
     * @param $filter
     */
    public function filter($key, $filter)
    {
        if ($key{0} == '/')
            $this->filtersReg[$key] = $filter;
        else
            $this->filters[$key] = $filter;
    }

    /**
     * возможность установки переменных
     * @param $key
     * @param $filter
     */
    public function setval($key, $value)
    {
        if(is_string($value))
            $this->variables[$key] = $this->escape($value);
        else {
            $this->error('unsupported too complex values');
        }
    }

    /** ************************************** фильтры ************************************* */
    /**
     *
     * реализация фильтра noescape
     */
    private function filter_noescape()
    {
        $this->noescape = true;
    }

    /**
     * реализация фильтра int - все фильтры, снимающие обязательный ескейпинг описываются так
     */
    function filter_int($s)
    {
        $this->noescape = true;
        return sprintf('(ctype_digit("".%1$s)?%1$s:"NOT_AN_INT")', $s);
    }

    /**
     * реализация фильтра int - все фильтры, снимающие обязательный ескейпинг описываются так
     */
    function filter_float($s)
    {
        $this->noescape = true;
        return sprintf('(is_float(%1$s)?%1$s:"NOT_A_FLOAT")', $s);
    }

    /**
     * filter_pair - формат пар в виде `key`='value'
     */
    private function filter_pair($s){
        $this->noescape = true; // будем ескейпить каждый элемент по отдельности.
        return __CLASS__ . '::_runtime_pair(' . $s . ')';
    }

    public static function _runtime_pair($s)
    {
        $result=array();
        foreach ($s as $k=>$v) {
            $result[]=sprintf('`%s`="%s"',self::escape($k),self::escape($v));
        }
        return implode(',',$result);
    }
    /**
     * реализация фильтра filter_join
     */
    private function filter_join($s, $match)
    {
        $this->noescape = true; // будем ескейпить каждый элемент по отдельности.
        return __CLASS__ . '::_runtime_join(' . $s . ',' . (empty($match[1]) ? '""' : $match[1]) . ')';
    }

    public static function _runtime_join($s, $delim)
    {
        foreach ($s as &$v) {
            if (!is_int($v))
                $v = "'" . addcslashes(self::escape($v), "'") . "'";
        }
        unset($v);
        return implode($delim, $s);
    }

    /** ************************************ парсинг ************************************* */

    /**
     * @param $found
     * @return string
     */

    private function parse_stm($found)
    {
        $list = explode('|', $found);
        $list[0]=trim($list[0]);
        $this->noescape = false;
        //$result = 'UNSUPPORTED';
        // so argument
        if (preg_match('/^\?(\d*)$/', $list[0], $m)) {
            $argument_number = $this->current_arg_number;
            if (!empty($m[1])) {
                if ($this->current_arg_number <= $m[1])
                    $this->current_arg_number++;
                $argument_number = $m[1];
            } else {
                $this->current_arg_number++;
            }
            $result = '$_' . $argument_number;
        } else if(array_key_exists($list[0],$this->variables)) {
            $this->noescape=true;
            $result = $this->variables[$list[0]];
        } else {
            $this->error(sprintf('unsupported argument "%s"', $m[1]));
            return '"UNSUPPORTED"';
        }

        for ($i = 1; $i < count($list); $i++) {
            $filter = trim($list[$i]);
            if (array_key_exists($filter, $this->filters)) {
                if (is_string($this->filters[$filter])) {
                    $result = sprintf($this->filters[$filter], $result);
                } elseif (is_callable($this->filters[$filter])) {
                    $result = call_user_func($this->filters[$filter], $result);
                }
            } else {
                $match = false;
                foreach ($this->filtersReg as $reg => $val) {
                    if (preg_match($reg, $filter, $m)) {
                        $result = call_user_func($val, $result, $m);
                        $match = true;
                        break;
                    }
                }
                if (!$match)
                    $this->error(sprintf('unsupported filter "%s"', $filter));
            }
        }
        if (!$this->noescape) {
            $result = '"\'".'.self::$escape.'('. $result.')."\'"';
        }
        return $result;
    }

    /**
     * по шаблону sql строится тело функции
     * @param $sql
     * @return string
     */
    function parse($sql)
    {
        // старт трансляции
        $this->current_arg_number = 1;
        $this->parsed_arguments = '';

        if (array_key_exists($sql, self::$cache))
            return self::$cache[$sql];
        $this->placeholders = array();
        // замена preg_replace на цикл со строковым сканированием
        $offset=0;
        $result=array();
        while(true){
            $pos=strpos($sql,'{{',$offset);
            if($pos!==false){
                if($pos>$offset){
                    $result[]="'".addcslashes(substr($sql,$offset,$pos-$offset), "'")."'";
                }
                $offset=$pos+2;
                $pos=strpos($sql,'}}',$offset);
                if($pos!==false){
                    if($pos>$offset){
                        $result[]=$this->parse_stm(substr($sql,$offset,$pos-$offset));
                    }
                    $offset=$pos+2;
                } else {
                    $this->error('wtf?');
                }
            } else {
                if(strlen($sql)>$offset)
                    $result[]="'".addcslashes(substr($sql,$offset), "'")."'";
                break;
            }

        }
        $args = array();
        for ($i = 1; $i < $this->current_arg_number; $i++)
            $args[] = '$_' . $i;

       // echo "return '" . $result . "';\n\n";
        self::$cache[$sql]= create_function(implode(',', $args), "return " . implode('.', $result) . ";");
        return self::$cache[$sql];
    }

}