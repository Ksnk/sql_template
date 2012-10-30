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
    function test_ArrayTemplate()
    {
        $sql = new sql_template();
        $func = $sql->parse('insert into xxx ({{?|keys|join(",")}}) values ({{?1|values|join(",")}}) ;');
        $this->assertEquals("insert into xxx ('one','two') values ('one_value','two_value') ;", $func(
            array('one' => 'one_value', 'two' => 'two_value')));
    }

    function test_IntTemplate()
    {
        $sql = new sql_template();
        $func = $sql->parse('select * from index where {{?|int}}<`field`;');

        $this->assertEquals("select * from index where 25<`field`;", $func(25));
    }
}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('sqltemplate_baseTest');
    PHPUnit_TextUI_TestRunner::run($suite);
}
?>