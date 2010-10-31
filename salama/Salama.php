<?php

function salama_autoloader($file) {
	foreach(array(null, 'backend', 'backend/nosql', 'backend/nosql/mongo', 'backend/sql', 'backend/sql/mysql', 'backend/sql/sqlite') as $dir) {
        $sub_dir = ($dir) ? "{$dir}/" : '';
	    if(file_exists(dirname(__FILE__). "/{$sub_dir}{$file}.php") && require_once(dirname(__FILE__). "/{$sub_dir}{$file}.php")) return true;
	}
}
spl_autoload_register("salama_autoloader");

class Salama extends SalamaController {
    public static $bootstrapped = false;
    public static $_settings = array();
	public static function bootstrap($settings=array()) {
        if(!isset($settings['config_dir'])) { 
            $settings['config_dir'] = dirname(__FILE__).'/../config';
        }
        # settings
        # @TODO convert settings.xml environment overrides into settings.php additions
        require_once $settings['config_dir'].'/settings.php';
		Salama::$_settings = SalamaSettings::get_settings();
        # models
		require_once Salama::$_settings['config'].'/models.php';

        # @TODO add DEBUG=True/False, and if True, re-generated always when doesn't exist.
        $required_build_file = Salama::$_settings['cache']."/SalamaData_".Salama::$_settings['env'].".php";
        #if(!file_exists($required_build_file)) {
            $runner = new SalamaRunner();
            $runner->run('build');
        #}
        require_once $required_build_file;
        Salama::$bootstrapped = true;
	}
}

function q($db=null, $sid=null) {
    if(!$sid) {
        $sid = md5(microtime(true) + rand());
    }
    if(!Salama::$bootstrapped) {
        Salama::bootstrap();
    }
    $salama = new Salama();
    return $salama;
}

// "query" object
class q {
    var $name = null;
    var $arguments = null;
    var $method = null;
    var $function_name = null;
    
    public function __construct($name, $arguments) {
        $this->function_name = $name;
        $this->name = $name;
        $this->arguments = $arguments;
        if(strpos($this->name, '__')) {
            # @TODO support model-namespacing
            # User__id__gt()
            $parts = explode('__', $this->name);
            $this->name = $parts[0];
            $this->method = $parts[1];
        } else {
            $this->method = 'eq'; # default to eq==equals
        }
    }
    # static model field access for query modeling
    public static function __callStatic($name, $arguments) {
        return new q($name, $arguments);
    }
}

?>