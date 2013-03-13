<?php
if (!defined('PHPUnit_MAIN_METHOD')) {
    ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(dirname(__FILE__)));
    require 'PHPUnit/Autoload.php';
}

include_once ("header.inc.php");


class benchmark extends PHPUnit_Framework_TestCase
{
    const MAX_REPEAT_NUMBER = 1000;
    /** @var PDO */
    var $pdo = null;
    /** @var sql_template */
    var $sql = null;
    /** @var DbSimple_Mysql */
    var $dbsimple=null;

    function start_pdo()
    {
        if (!empty($this->pdo))
            return;
        /* Connect to an ODBC database using driver invocation */
        $dsn = 'mysql:dbname=cms;host=localhost';
        $user = 'root';
        $password = '';

        $this->pdo = new PDO($dsn, $user, $password);
    }

// Код обработчика ошибок SQL.
    function databaseErrorHandler($message, $info)
    {
        // Если использовалась @, ничего не делать.
        if (!error_reporting()) return;
        // Выводим подробную информацию об ошибке.
        $s= "SQL Error: $message<br><pre>".print_r($info,true)."</pre>";
        ENGINE::error($s);
    }

    function start_dbsimple()
    {
        if (!empty($this->dbsimple))
            return;
        $connect="mysql://root:@localhost/cms";
        require_once "DBSimple/Generic.php";
// Устанавливаем соединение.
        /*
        */
            //echox 'test1';//$connect; //mysql://dbu_xilen_7:Ivnsqk0eCru@mysql.xilen.z8.ru
        $this->dbsimple = DbSimple_Generic::connect($connect);
        $this->dbsimple->setIdentPrefix('xsite_');
        $this->dbsimple->setErrorHandler(array($this,'databaseErrorHandler'));
//            $DATABASE->setLogger('myLogger');
        $this->dbsimple->select('set NAMES "utf8";');// High  magic!!!!!!!!!!!!!!!!!
    }

    function start_tpl()
    {
        if (!empty($this->sql))
            return;

        $this->sql = new sql_child();
    }

    function testPrepareStatementssetNames1000times()
    {
        echo 'testing set names utf8 - но substitution'."\n";
        $this->start_tpl();
        $this->start_pdo();
        $this->start_dbsimple();
        // to fill all cached structure
        // test simple exec
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $this->pdo->exec('set names utf8; -- comment ' . $i);
        }
        $time0 = microtime(true) - $time;
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $func = $this->sql->parse('set names utf8; -- comment ' . $i);
            $this->pdo->exec($func(''));
        }
        $time1 = microtime(true) - $time;

        //$sth = $this->pdo->prepare('set names :code');
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $sth = $this->pdo->prepare('set names utf8; -- comment ' . $i);
            $sth->execute();
        }
        $time2 = microtime(true) - $time;
        //$sth = $this->pdo->prepare('set names :code');
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $this->dbsimple->select('set names utf8; -- comment ' . $i,1);
        }
        $time3 = microtime(true) - $time;

        printf("so spent %f sec for sql, %f sec for pdo, %f sec for dbsimple(%f)\n"
            , $time1-$time0, $time2-$time0, $time3-$time0, $time0);
        $this->assertTrue(true);
    }

    function testPrepareStatements1000times()
    {
        echo 'testing select * from xsite_users where `id`="1"'."\n";
        $this->start_tpl();
        $this->start_pdo();
        $this->start_dbsimple();
        // to fill all cached structure
        // test simple exec
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $this->pdo->exec('select * from xsite_users where `id`=1');
        }
        $time0 = microtime(true) - $time;
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $func = $this->sql->parse('select * from xsite_users where `id`={{?|int}}; -- comment ' . $i);
            $this->pdo->exec($func('', 1));
        }
        $time1 = microtime(true) - $time;

        //$sth = $this->pdo->prepare('set names :code');
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $sth = $this->pdo->prepare('select * from xsite_users where `id`=:id; -- comment ' . $i);
            $sth->bindValue(':id', 1, PDO::PARAM_INT);
            $sth->execute();
        }
        $time2 = microtime(true) - $time;
        //$sth = $this->pdo->prepare('set names :code');
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $this->dbsimple->select('select * from xsite_users where `id`=?d; -- comment ' . $i,1);
        }
        $time3 = microtime(true) - $time;

        printf("so spent %f sec for sql, %f sec for pdo, %f sec for dbsimple(%f)\n"
            , $time1-$time0, $time2-$time0, $time3-$time0, $time0);
        $this->assertTrue(true);
    }

    function testPrepareStatements1000timesString()
    {
        echo 'testing select * from xsite_users where `name`="admin"'."\n";
        $this->start_tpl();
        $this->start_pdo();
        $this->start_dbsimple();
        // to fill all cached structure
        // test simple exec
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $this->pdo->exec('select * from xsite_users where `name`="admin"');
        }
        $time0 = microtime(true) - $time;
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $func = $this->sql->parse('select * from xsite_users where `name`={{?}}; -- comment ' . $i);
            $this->pdo->exec($func('', 1));
        }
        $time1 = microtime(true) - $time;

        //$sth = $this->pdo->prepare('set names :code');
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $sth = $this->pdo->prepare('select * from xsite_users where `name`=:name; -- comment ' . $i);
            $sth->bindValue(':name', 'admin', PDO::PARAM_STR);
            $sth->execute();
        }
        $time2 = microtime(true) - $time;
        //$sth = $this->pdo->prepare('set names :code');
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $this->dbsimple->select('select * from xsite_users where `id`=?; -- comment ' . $i,'admin');
        }
        $time3 = microtime(true) - $time;

        printf("so spent %f sec for sql, %f sec for pdo, %f sec for dbsimple(%f)\n"
            , $time1-$time0, $time2-$time0, $time3-$time0, $time0);
        $this->assertTrue(true);
    }

    function testTranslation1000times()
    {
        $this->start_tpl();
        $this->start_pdo();
        // test simple exec
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $this->pdo->exec('set names utf8');
        }
        $time0 = microtime(true) - $time;

        $func = $this->sql->parse('set names {{?}} ;');
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $this->pdo->exec($func('', 'utf8'));
        }
        $time1 = microtime(true) - $time;

        $sth = $this->pdo->prepare('set names :code');
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $sth->bindValue(':code', 'utf8', PDO::PARAM_STR);
            $sth->execute();
        }
        $time2 = microtime(true) - $time;

        printf("so spent %f sec for sql, %f sec for pdo (%f)\n"
            , $time1, $time2, $time0);
        $this->assertTrue(true);
    }
}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('benchmark');
    PHPUnit_TextUI_TestRunner::run($suite);
}
?>