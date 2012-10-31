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

    function test_unknown_filter_error()
    {
        try {
            $sql = sql_template::getInstance();
            $sql->setval('prefix', 'mixnfix');
            $func = $sql->parse('SELECT * FROM {{prefix}}_user LIMIT {{?|xxx}} ');
            $this->assertEquals("SELECT * FROM mixnfix_user LIMIT 10 ", $func('two_value'));
        } catch (Exception $expected) {
            $this->assertEquals('unsupported filter "xxx"', $expected->getMessage());
            return;
        }
        $this->fail('An expected exception has not been raised.');
    }

    function test_incorrect_filter_definition_error()
    {
        try {
            $sql = sql_template::getInstance();
            $sql->setval('prefix', 'mixnfix');
            $func = $sql->parse('SELECT * FROM {{prefix}}_user LIMIT {{?|faked}} ');
            $this->assertEquals("SELECT * FROM mixnfix_user LIMIT 10 ", $func('two_value'));
        } catch (Exception $expected) {
            $this->assertEquals('Could not create function \nfunction($_1){\n\treturn \'SELECT * FROM \'.mixnfix.\'_user LIMIT \'."\'".mysql_escape_string("nothing here!)."\'".\' \'\n}\n.', $expected->getMessage());
            return;
        }
        $this->fail('An expected exception has not been raised.');
    }

    function test_incorrect_int_param_error()
    {
        // so the func cant build a string with wrong arguments.
        try {
            $sql = sql_template::getInstance();
            $func = $sql->parse('so i is an array value {{?|keys}}');
            $this->assertEquals("so i is an array value NOT_AN_INT", $func('two_value'));
        } catch (Exception $expected) {
            $this->assertEquals('array_keys() expects parameter 1 to be array, string given', $expected->getMessage());
            return;
        }
        $this->fail('An expected exception has not been raised.');
    }

    function test_incorrect_array_param_error()
    {
        // so the mysql will return an error with incorrect arguments.
        $sql = sql_template::getInstance();
        $func = $sql->parse('so i is an array value {{?|int}}');
        $this->assertEquals("so i is an array value NOT_AN_INT", $func('two_value'));
    }

}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('sqltemplate_ErrorTest');
    PHPUnit_TextUI_TestRunner::run($suite);
}
?>