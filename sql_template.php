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
 */
class sql_template
{
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
     * функция выдчи ошибки - недоразвита и, вероятно, не нужна.
     * @param $msg
     */
    protected function error($msg)
    {
        echo $msg;
    }

    /**
     * заполнение фильтров
     */
    public function __construct()
    {
        $this->filters = array(
            'escape' => 'mysql_escape_string(%s)', //'mysql_real_escape_string(%s)', //TODO: только для тестов!!
            'noescape' => array($this, 'filter_noescape'),
            'keys' => 'array_keys(%s)',
            'values' => 'array_values(%s)',
            'int' => array($this, 'filter_int'),
            'float' => array($this, 'filter_float'),
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
     * реализация фильтра noescape
     */
    private function filter_join($s, $match)
    {
        $this->noescape = true; // будем ескейпить каждый элемент по отдельности.
        return __CLASS__ . '::_runtime_join(' . $s . ',' . (empty($match[1]) ? '""' : $match[1]) . ')';
    }

    public static function _runtime_join($s, $delim)
    {
        foreach ($s as &$v) {
            if (!ctype_digit($v))
                $v = "'" . addcslashes(mysql_escape_string($v), "'") . "'"; //TODO: только для тестов!!
        }
        return implode($delim, $s);
    }

    /** ************************************ парсинг ************************************* */

    /**
     * @param $found
     * @return string
     */

    private function parse_placeholders($found)
    {
        $list = explode('|', $found[1]);
        // so argument
        if (preg_match('/^\s*\?(\d*)\s*$/', $list[0], $m)) {
            $argument_number = $this->current_arg_number;
            if (!empty($m[1])) {
                if ($this->current_arg_number <= $m[1])
                    $this->current_arg_number++;
                $argument_number = $m[1];
            } else {
                $this->current_arg_number++;
            }
        } else {
            $this->error(sprintf('unsupported argument "%s"', $m[1]));
            return '"UNSUPPORTED"';
        }
        $result = '$_' . $argument_number;
        $this->noescape = false;
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
            $result = $result = call_user_func($this->filters['escape'], $result);
        }
        $this->placeholders[] = $result;
        return "@" . count($this->placeholders) . "@";
    }

    function placeholders_back($m)
    {
        return "'." . $this->placeholders[$m[1] - 1] . ".'";
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
        $result = preg_replace_callback('/{{(.*?)}}/', array($this, 'parse_placeholders'), $sql);
        $result = addcslashes($result, "'");
        $result = preg_replace_callback('/@(\d+)@/', array($this, 'placeholders_back'), $result);
        $args = array();
        for ($i = 1; $i < $this->current_arg_number; $i++)
            $args[] = '$_' . $i;
        return create_function(implode(',', $args), "return '" . $result . "';");
    }

}