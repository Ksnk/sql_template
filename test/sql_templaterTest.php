<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Сергей
 * Date: 30.10.12
 * Time: 12:18
 * To change this template use File | Settings | File Templates.
 */

if (!defined('PHPUnit_MAIN_METHOD')) {
    ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(dirname(__FILE__)));
    require 'PHPUnit/Autoload.php';
}

include ('header.inc.php');

class sqltemplate_baseTest extends PHPUnit_Framework_TestCase
{
    function &getSql($rebuild=false)
    {
        static $cache;
        if (!!$rebuild || empty($cache)) {
            if(is_string($rebuild))
                $cache= new $rebuild();
            else
                $cache=new sql_template();
        }
        return $cache;
    }


    function test_multySql()
    {
        $tpl = 'UPDATE `rules`
SET `used_at` = NOW(), `times_used` = `times_used` + CASE `id`
{{?|format("WHEN %d THEN %d","
")}}
END
WHERE id IN({{?1|keys|join(",")}})';

        $sql = 'UPDATE `rules`
SET `used_at` = NOW(), `times_used` = `times_used` + CASE `id`
WHEN 1 THEN 10
WHEN 2 THEN 13
WHEN 5 THEN 123
WHEN 17 THEN 43
END
WHERE id IN(1,2,5,17)';

        $data = array(1 => 10, 2 => 13, 5 => 123, 17 => 43);

        $func = $this->getSql('sql_child')->parse($tpl);
        $this->assertEquals($sql, $func('',$data));
    }

    function test_prefix_Template()
    {
        $sql = $this->getSql(true);
        $sql->regcns('prefix', 'mixnfix');
        $func = $sql->parse('SELECT * FROM {{prefix}}_user LIMIT 10 ');
        $this->assertEquals("SELECT * FROM mixnfix_user LIMIT 10 ", $func('',
            array('one' => 'one_value', 'two' => 'two_value')));
    }

    function test_child()
    {
        $sql = $this->getSql('sql_child');
        $func = $sql->parse('SELECT * FROM ?_user WHERE user_id={{?|somefilter}}');
        $this->assertEquals('SELECT * FROM ?_user WHERE user_id=\'nothing here!\'', $func('','\\/#?@1`12"4'));
    }

    function test_escapedvalue()
    {
        $sql = $this->getSql(true);
        $func = $sql->parse('SELECT * FROM ?_user WHERE user_id={{?}}');
        $this->assertEquals('SELECT * FROM ?_user WHERE user_id=\'\\\\/#?@1`12\"4\'', $func('','\\/#?@1`12"4'));
    }

    function test_join_Template()
    {
        $sql = $this->getSql(true);
        $func = $sql->parse('SELECT name FROM tbl WHERE id IN({{?|join(",")}})');
        $this->assertEquals("SELECT name FROM tbl WHERE id IN(1,101,303)", $func('',
            array(1, 101, 303)));
    }

    function test_insert_set_Template()
    {
        $sql = $this->getSql(true);
        $func = $sql->parse('insert into xxx set {{?|pair}} ;');
        $this->assertEquals("insert into xxx set `one`=\"one_value\",`two`=\"two_value\" ;", $func('',
            array('one' => 'one_value', 'two' => 'two_value')));
    }

    function test_insert_key_valuesTemplate()
    {
        $sql = $this->getSql(true);
        $func = $sql->parse('insert into xxx ({{?|keys|join(",")}}) values ({{?1|values|join(",")}}) ;');
        $this->assertEquals("insert into xxx ('one','two') values ('one_value','two_value') ;",
            $func('',array('one' => 'one_value', 'two' => 'two_value'))
        );
    }

    function test_IntTemplate()
    {
        $sql = $this->getSql(true);
        $func = $sql->parse('select * from index where {{?|int}}<`field`;');

        $this->assertEquals("select * from index where 25<`field`;", $func('',25));
    }

    function test_manyargs()
    {
        $sql = $this->getSql(true);
        $func = $sql->parse('{{?}}-1 {{?3}}-3 {{?}} - 2 {{?}} -3');
        $this->assertEquals("'1'-1 '3'-3 '2' - 2 '3' -3", $func('',
            1, 2, 3));
    }

}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('sqltemplate_baseTest');
    PHPUnit_TextUI_TestRunner::run($suite);
}
?>