<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Сергей
 * Date: 06.04.13
 * Time: 10:19
 * To change this template use File | Settings | File Templates.
 */
    if (!defined('PHPUnit_MAIN_METHOD')) {
        ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(dirname(__FILE__)));
        require 'PHPUnit/Autoload.php';
    }

    include ('header.inc.php');

/**
 * тестирование на собачках
 */

class xDBTest extends PHPUnit_Framework_TestCase
{

    function testXDatabase1(){
        $this->assertEquals(ENGINE::db('debug')->selectRow('select * from ?_tourplayers'),
            Array('ID_PLAYER' => 35
    ,'ID_TOURNAMENT' => 25
    ,'NUMBER' => 2
    ,'DESCR' =>''
    ,'RES1' => 0
    ,'RES2' => 0
    ,'RES3' => 0
    ,'RES4' => 0
    ,'RES5' => 0
    ,'RES6' => 0
));
    }

    function testXDatabase2(){
        $x=0;
        foreach(ENGINE::db('debug')->selectLong('select * from ?_tourplayers') as $v){
            $x++;
        };
        $this->assertEquals(437,$x);
    }


}

include_once(SYSTEM_PATH . "/xDatabase.php");
ENGINE::set_option(array(
    'engine.aliaces' =>
    array (
        'Database' => 'xDatabaseLapsi',
    ),
    'database.host' => 'localhost',
    'database.user' => 'root',
    'database.password' => '',
    'database.base' => 'cms',
    'database.prefix' => 'darts_'
));

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('xDBTest');
    PHPUnit_TextUI_TestRunner::run($suite);
}