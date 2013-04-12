<?php
/**
 * Created by JetBrains PhpStorm.
 * User: ׁונדוי
 * Date: 06.04.13
 * Time: 10:19
 * To change this template use File | Settings | File Templates.
 */
    if (!defined('PHPUnit_MAIN_METHOD')) {
        ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(dirname(__FILE__)));
        require 'PHPUnit/Autoload.php';
    }

    include ('header.inc.php');



class xLapsiTest extends PHPUnit_Framework_TestCase
{

    function getXDatabase($option=''){
        static $xDatabase=null;
        if(is_null($xDatabase)) {
            $data=preg_replace(array('/^\s*<\?php/','/\?>\s*^/'),array(''),
                str_replace('mysql_real_escape_string','mysql_escape_string',
                    file_get_contents(SYSTEM_PATH . "/xDatabase.php")
                ));
            eval($data);
            $xDatabase=new xDatabaseLapsi('noinit');
        }
        if(!empty($option))
           $xDatabase->set_option($option);

        return $xDatabase;
    }

    function testXDatabase1(){
        $db=$this->getXDatabase();
        $this->assertEquals('insert into `x_table` (`one`,`two`,`three`) values (1,2,"מבכמל")',
            $db->_(array('insert into ?k (?(?k)) values (?2(?2))','x_table'
            ,array('one'=>1,'two'=>2,'three'=>'מבכמל'))));
    }

    function testXDatabase2(){
        $db=$this->getXDatabase();
        $this->assertEquals('update `x_table` set `one`=1,`two`=2,`three`="מבכמל" where `id`=5',
            $db->_(array('update ?k set ?[?k=?] where `id`=?d','x_table'
            ,array('one'=>1,'two'=>2,'three'=>'מבכמל'),'5')));
    }

    function testXDatabase3(){
        $db=$this->getXDatabase();
        $this->assertEquals('insert into `x_table` (`one`,`two`,`three`) values (1,2,"מבכמל")
            on duplicate key set `one`=1,`two`=2,`three`="מבכמל"',
            $db->_(array('insert into ?k (?[?k]) values (?2(?2))
            on duplicate key set ?2[?k=?]','x_table'
            ,array('one'=>1,'two'=>2,'three'=>'מבכמל'))));
    }

    function testXDatabase4(){
        $db=$this->getXDatabase();
        $this->assertEquals('insert into `x_table` (`one`,`two`,`three`) values (1,2,"מבכמל")
            on duplicate key set `one`=values.`one`,`two`=values.`two`,`three`=values.`three`',
            $db->_(array('insert into ?k (?(?k)) values (?2[?2])
            on duplicate key set ?2[?k=values.?1k]','x_table'
            ,array('one'=>1,'two'=>2,'three'=>'מבכמל')))
        );
    }

    function testXDatabase5(){
        $db=$this->getXDatabase();
        $x=array(
            array('x'=>1,'y'=>2,'z'=>3),
            array('x'=>1,'y'=>2,'z'=>3),
            array('x'=>1,'y'=>2,'z'=>3),
            array('x'=>1,'y'=>2,'z'=>3),
            array('x'=>1,'y'=>2,'z'=>3),
            array('x'=>1,'y'=>2,'z'=>3),
            array('x'=>1,'y'=>2,'z'=>3),
         );
         $part=array();
         foreach($x as $xx)
             $part[]=$db->_(array('(?(?2))
 ',$xx));
         $this->assertEquals('insert into `table` (`x`,`y`,`z`)
 values (1,2,3)
 ,(1,2,3)
 ,(1,2,3)
 ,(1,2,3)
 ,(1,2,3)
 ,(1,2,3)
 ,(1,2,3)
 ;',
             $db->_(array('insert into ?k (?[?k])
 values ?3(?2x);','table',$x[0],$part)));
    }

    function testDebug4(){
        $db=$this->getXDatabase();
        $this->assertEquals('insert into `x_table` (`one`,`two`,`three`) values (1,2,"מבכמל")
            on duplicate key set `one`=values.`one`,`two`=values.`two`,`three`=values.`three`',
            $db->_(array('insert into ?k (?[?k]) values (?2[?2])
            on duplicate key set ?2[?k=values.?1k]','x_table'
            ,array('one'=>1,'two'=>2,'three'=>'מבכמל')))
        );
    }



}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('xLapsiTest');
    PHPUnit_TextUI_TestRunner::run($suite);
}