<?php

include_once ("header.inc.php");


class profile
{
    const MAX_REPEAT_NUMBER = 1000;
    /** @var sql_template */
    var $sql = null;
    /** @var PDO */
    var $pdo = null;

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
    function start_tpl()
    {
        if (!empty($this->sql))
            return;

        $this->sql = new sql_child();
    }

    function run()
    {
        echo 'testing set names utf8 - но substitution'."\n";
        $this->start_tpl();
        $this->start_pdo();
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


        printf("so spent %f sec for sql(%f)\n"
            , $time1-$time0, $time0);
        //$this->assertTrue(true);
    }

}

$profile=new profile();
$profile->run();
