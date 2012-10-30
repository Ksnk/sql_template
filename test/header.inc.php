<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Сергей
 * Date: 09.10.12
 * Time: 14:29
 * To change this template use File | Settings | File Templates.
 */

/**
 * определение путей системы
 */
//define('INDEX_DIR',__DIR__);
define('SYSTEM_PATH', dirname(dirname(__FILE__)));
//define('SITE_PATH',SYSTEM_PATH);
//define('TEMPLATE_PATH',realpath(SITE_PATH.'/template/'));
//define('ROOT_URI','/projects/cms/build/web/index.php');

include_once (SYSTEM_PATH . "/sql_template.php");
