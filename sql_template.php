<?php
/**
 * трансляция sql шаблонов
 * <%=point('hat','jscomment');





%>
 */
/**
 * Задачей класса является трансляция строки SQL в код функции для create_function.
 * Код обязан получать параметры, как обычно
 * ->query(sql,$one,$two,$three)
 * получится
 * $sqltpl=sql_template::getInstance();
 * $func= $sqltpl->parse(sql);
 * $args=func_get_args();
 * $result= mysql_query(call_user_func_array($func,array_shift($args)));
 * есть рудиментарное кэширование для повторных шаблонов.
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

    /**
     * для простоты замены функции эскейпинга
     * @var string
     */
    static $escape = 'mysql_real_escape_string';

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
    private $args_count = 1;

    /**
     * внутренний флаг фильтра - нужно вставлять escape в конце или уже не нужно
     * @var
     */
    protected $noescape;

    /**
     * массиив фильтров
     * @var array
     */
    protected $filters;

    /**
     * массиив фильтров, определеных регуляркой - фильтры с параметрами
     * @var array
     */
    protected $filtersReg;

    /**
     * массив констант, которые вставляются в строку без эскейпинга
     * @var array
     */
    protected $constant;
    /**
     * массив переменных, для явной подстановки в запрос
     * @var array
     */
    protected $variables;

    /**
     * функция выдачи ошибки. Просто бросим Exception и пусть весь мир подождет.
     * @param $msg
     */
    protected function error($msg)
    {
        throw new Exception ($msg);
    }

    /**
     * внутренняя функция
     * @static
     * @param $s
     * @return mixed
     */
    protected static function escape($s)
    {
        return call_user_func(self::$escape, $s);
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
            'pair' => array($this, 'filter_format'),
//            'format' => array($this, 'filter_format'),
        );
        $this->filtersReg = array(
            '/^join$/' => array($this, 'filter_join'),
            '/^join\s*\(([^\)]+)\)$/' => array($this, 'filter_join'),
            '/^pair\s*$/' => array($this, 'filter_format'),
            '/^format\s*\(([^\),]+)(?:,([^\)]+))?\)$/' => array($this, 'filter_format'),
        );
    }

    /**
     * добавить новый фильтр в комплект фильтров. Если начинается с / - cчитается регуляркой
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
    public function regcns($key, $value)
    {
        if (is_string($value) || is_int($value))
            $this->constant[$key] = $value;
        else {
            $this->error('unsupported too complex values');
        }
    }

    /**
     * возможность установки переменных
     * @param $key
     * @param $filter
     */
    public function regvar($key, &$value)
    {
        if (is_string($value))
            $this->variables[$key] = $value;
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
    private function filter_format($s, $match=array())
//$format='`%s`="%s"',$join=',')
    {
        if(!isset($match[1])) $match[1]='\'`%s`="%s"\'';
        if(!isset($match[2])) $match[2]='\',\'';
        $this->noescape = true; // будем ескейпить каждый элемент по отдельности.
        return __CLASS__ . '::_runtime_format(' . $s
            . ','.$match[1].','.$match[2].')';
    }

    public static function _runtime_format($s,$format,$join)
    {
        $result = array();
        foreach ($s as $k => $v) {
            $result[] = sprintf($format, self::escape($k), self::escape($v));
        }
        return implode($join, $result);
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
                $v = "'" . addcslashes(self::escape($v), "'\\") . "'";
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
        $name = trim($list[0]);
        $this->noescape = false;
        $result = 'UNSUPPORTED';
        // so argument
        $this->args_count = 0;
        if (preg_match('/^\?(\d*)$/', $name, $m)) {
            $argument_number = $this->current_arg_number;
            if (!empty($m[1])) {
                $argument_number = $m[1]+1;
            } else {
                $this->current_arg_number++;
            }
            $result = '$_' . $argument_number;
            if ($argument_number > $this->args_count) $this->args_count = $argument_number;
        } else if (array_key_exists($name, $this->constant)) {
            $this->noescape = true;
            if(!empty($this->constant[$name])){
                if($this->constant[$name]{0}=="'"){
                    $result=$this->constant[$name];
                } else {
                    $result = "'" . addcslashes($this->constant[$name], "'\\") . "'";
                }
            }
        } else if (array_key_exists($name, $this->variables)) {
            $this->noescape = true;
            if ($this->variables[$name]{0})
                $result = "'" . addcslashes($this->variables[$name], "'\\") . "'";
        } else {
            $this->error(sprintf('unsupported argument "%s"', $m[1]));
            return '"UNSUPPORTED"';
        }

        for ($i = 1; $i < count($list); $i++) {
            $filter = trim($list[$i]);
            if (array_key_exists($filter, $this->filters)) {
                if (is_string($this->filters[$filter])) {
                    $this->noescape = true;
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
            $result = '"\'".' . self::$escape . '(' . $result . ')."\'"';
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
        if (array_key_exists($sql, self::$cache))
            return self::$cache[$sql];
        // старт трансляции
        $this->current_arg_number = 2;
        $this->parsed_arguments = '';

        $this->placeholders = array();
        // замена preg_replace на цикл со строковым сканированием
        $offset = 0;
        $result = array();
        while (true) {
            $pos = strpos($sql, '{{', $offset);
            if ($pos !== false) {
                if ($pos > $offset) {
                    $result[] = "'" . addcslashes(substr($sql, $offset, $pos - $offset), "'\\") . "'";
                }
                $offset = $pos + 2;
                $pos = strpos($sql, '}}', $offset);
                if ($pos !== false) {
                    if ($pos > $offset) {
                        $result[] = $this->parse_stm(substr($sql, $offset, $pos - $offset));
                    }
                    $offset = $pos + 2;
                } else {
                    $this->error('wtf?');
                }
            } else {
                if (strlen($sql) > $offset)
                    $result[] = "'" . addcslashes(substr($sql, $offset), "'\\") . "'";
                break;
            }

        }
        $args = array();
        for ($i = 1; $i <= $this->args_count; $i++)
            $args[] = '$_' . $i;

        echo "function( " .implode(',', $args)."){ return ".implode('.', $result) . ";}\n\n";
        $fnc = @create_function(implode(',', $args), "return " . implode('.', $result) . ";");
        if (empty($fnc)) {
            $this->error(sprintf('Could not create function \nfunction(%s){\n\t%s\n}\n.',
                implode(',', $args), "return " . implode('.', $result)
            ));
        }
        self::$cache[$sql] = $fnc;
        return $fnc;
    }

}