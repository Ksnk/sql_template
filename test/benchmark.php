<?php
if (!defined('PHPUnit_MAIN_METHOD')) {
    ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(dirname(__FILE__)));
    require 'PHPUnit/Autoload.php';
}

include_once ("header.inc.php");


class benchmark extends PHPUnit_Framework_TestCase
{
    const MAX_REPEAT_NUMBER = 1000;

    function start_pdo()
    {
        if (!empty($this->pdo))
            return;
        /* Connect to an ODBC database using driver invocation */
        $dsn = 'mysql:dbname=tmp;host=localhost';
        $user = 'root';
        $password = '';

        $this->pdo = new PDO($dsn, $user, $password);
    }

    function start_tpl()
    {
        if (!empty($this->sql))
            return;

        $this->sql = new sql_child();
    }

    function testPrepareStatements1000times()
    {
        $this->start_tpl();
        $this->start_pdo();
        // test simple exec
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $this->pdo->exec('set names utf8');
        }
        $time0 = microtime(true) - $time;
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $func = $this->sql->parse('set names {{?}} ; -- comment ' . $i);
            $this->pdo->exec($func('', 'utf8'));
        }
        $time1 = microtime(true) - $time;

        //$sth = $this->pdo->prepare('set names :code');
        $time = microtime(true);
        for ($i = 0; $i < self::MAX_REPEAT_NUMBER; $i++) {
            $sth = $this->pdo->prepare('set names :code; -- comment ' . $i);
            $sth->bindValue(':code', 'utf8', PDO::PARAM_STR);
            $sth->execute();
        }
        $time2 = microtime(true) - $time;

        printf("so spent %f sec for sql, %f sec for pdo(%f)\n"
            , $time1-$time0, $time2-$time0, $time0);
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