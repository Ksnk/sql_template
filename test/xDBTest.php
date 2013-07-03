<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Сергей
 * Date: 06.04.13
 * Time: 10:19
 * To change this template use File | Settings | File Templates.
 */
    if (!function_exists('phpunit_autoload')) {
        ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(dirname(__FILE__)));
        require 'PHPUnit/Autoload.php';
    }

    include_once ('header.inc.php');

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
    function testXDatabase3(){
        $x=-5;
        foreach(ENGINE::db('debug')->selectLong('select * from ?_tourplayers where false') as $v){
            $x++;
        };
        $this->assertEquals(-5,$x);
    }
/*
    function testInsertLong(){
        $table="test";
        ENGINE::db()->query("drop table if exists ?k;",$table);
        $test=array(
            'ID'=>'int(11) NOT NULL auto_increment',
            'name'=>'varchar(255) NOT NULL',
            'ival'=>'int(11) default NULL',
            'sval'=>'varchar(255) NOT NULL',
            'tval'=>'text',
        );
        $keys='PRIMARY KEY  (`id`,`name`),
        KEY `sval` (`sval`)';

        $x=ENGINE::db()->query('create table ?x (?[
        ?k ?x],
        ?x );',$table,$test,$keys);

        ENGINE::db()->insertLong('insert into ?k (?[?k]) values %s
         on duplicate key set ?2[?k=values.?1k];',$data);

        $this->assertEquals(-5,$x);
    }
*/
    function testXDatabase4(){
        $x=-5;
        ENGINE::db('debug')->insert('insert into ?_tourplayers (?1[?k]) values (?1[?2])'.
'on duplicate key update ?1[?1k=VALUES(?1k)];'
,Array('ID_PLAYER' => 35
            ,'ID_TOURNAMENT' => 25
            ,'NUMBER' => 2
            ,'DESCR' =>''
            ,'RES1' => 0
            ,'RES2' => 0
            ,'RES3' => null
            ,'RES4' => ''
            ,'RES5' => 0
            ,'RES6' => 0
            ));
        $this->assertEquals(-5,$x);
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