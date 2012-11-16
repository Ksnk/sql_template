<?php
/**
 * Класс - затычка для тестирования
 */

class ENGINE {

    public static $options=array(

    ) ;

    static function option($name){
        if(array_key_exists($name,self::$options)){
            return self::$options;
        }  else {
            return '';
        }
    }
    static function debug($msg){
        echo 'DEBUG: '.$msg;
    }
    static function error($msg){
        throw new Exception($msg);
    }
}

