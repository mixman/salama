<?php

class SalamaSettings {    
    public static function get_settings() {
        $base_dir = dirname(__FILE__).'/..';
        $settings = array(
        'config' => $base_dir.'/config',
        'cache' => '/tmp',
        'env' => 'testing'
        );        
        return $settings;
    }

}

?>
