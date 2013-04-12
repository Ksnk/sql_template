<?php
/**
 * Класс - затычка для тестирования
 */

class ENGINE {

    public static $options=array(

    ) ;

    static function &db($option=''){
        /** @var xDatabaseLapsi */
        static $db;
        if(!isset($db)){
            $aliace=self::option('engine.aliaces');
            $class=$aliace['Database'];
            $db= new $class();
        }
        if(!empty($option))
            $db->set_option($option);
        return $db;
    }

    static function set_option($option){
        self::$options=array_merge(self::$options,$option);
    }

    static function option($name,$default=''){
        if(array_key_exists($name,self::$options)){
            return self::$options[$name];
        }  else {
            return $default;
        }
    }
    static function debug($msg){
        echo 'DEBUG: '.$msg;
    }
    static function error($msg){
        throw new Exception($msg);
    }
}

