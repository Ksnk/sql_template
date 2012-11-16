<?php
/**
 * тестирование ошибочных ситуаций
 */

if (!defined('PHPUnit_MAIN_METHOD')) {
    ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(dirname(__FILE__)));
    require 'PHPUnit/Autoload.php';
}

include ('header.inc.php');

class sqltemplate_ErrorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param bool $rebuild
     * @return sql_template
     */
    function &getSql($rebuild=false)
    {
        static $cache;
        if (!$rebuild || empty($cache)) {
            $cache=new sql_template();
        }
        return $cache;
    }

    function test_unknown_filter_error()
    {
        try {
            $sql = $this->getSql();
            $sql->regcns('prefix', 'mixnfix');
            $func = $sql->parse('SELECT * FROM {{prefix}}_user LIMIT {{?|xxx}} ');
            $this->assertEquals("SELECT * FROM mixnfix_user LIMIT 10 ", $func('','two_value'));
        } catch (Exception $expected) {
            $this->assertEquals('unsupported filter "xxx"', $expected->getMessage());
            return;
        }
        $this->fail('An expected exception has not been raised.');
    }

    function test_incorrect_filter_definition_error()
    {
        try {
            $sql = $this->getSql();
            $sql->regcns('prefix', 'mixnfix');
            $func = $sql->parse('SELECT * FROM {{prefix}}_user LIMIT {{?|faked}} ');
            $this->assertEquals("SELECT * FROM mixnfix_user LIMIT 10 ", $func('','two_value'));
        } catch (Exception $expected) {
            $this->assertEquals('unsupported filter "faked"', $expected->getMessage());
            return;
        }
        $this->fail('An expected exception has not been raised.');
    }

    function test_incorrect_int_param_error()
    {
        // so the func cant build a string with wrong arguments.
        try {
            $sql = $this->getSql(true);
            $func = $sql->parse('so i is an array value {{?|keys}}');
            $this->assertEquals("so i is an array value NOT_AN_INT", $func('','two_value'));
        } catch (Exception $expected) {
            $this->assertRegExp('/^array_keys\(\) expects parameter 1 to be array, string given/', $expected->getMessage());
            return;
        }
        $this->fail('An expected exception has not been raised.');
    }

    function test_incorrect_array_param_error()
    {
        // so the mysql will return an error with incorrect arguments.
        $sql = $this->getSql();
        $func = $sql->parse('so i is an array value {{?|int}}');
        $this->assertRegExp("/^so i is an array value NOT_AN_INT$/", $func('','two_value'));
    }

}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('sqltemplate_ErrorTest');
    PHPUnit_TextUI_TestRunner::run($suite);
}
?>