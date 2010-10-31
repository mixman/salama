<?php

class SalamaDatabaseManager {

    public static $backend_global = array();
    public static $backends = array();
    public static $databases = array();
    public static $_settings = array();

    public function __construct() {
        $xml = simplexml_load_string(file_get_contents(Salama::$_settings['config'] . '/databases.xml'));

        $configuration = json_decode(json_encode($xml->xpath("//backend/configuration[not(@*)]")), true);
        self::$backend_global = $configuration[0];
        foreach (json_decode(json_encode($xml->xpath("//backend/configuration[(@*)]")), true) as $backend) {
            $backend_name = $backend['@attributes']['name'];
            unset($backend['@attributes']);
            self::$backends[$backend_name] = array_merge(self::$backend_global, $backend);
        }

        foreach (json_decode(json_encode($xml->xpath(sprintf("//configuration[@environment='%s']/database", Salama::$_settings['env']))), true) as $db) {
            $dsn = SalamaDatabaseManager::parse_dsn($db['dsn']);
            $database = sprintf("Salama%sDatabase", ucfirst($dsn['scheme']));
            $obj = new $database($db['@attributes']['name'], $dsn);
            self::$databases[$db['@attributes']['name']] = $obj;
        }
    }

    public static function getCurrentBackend() {
        $default_database = self::getDefaultDatabaseName();
        $db = self::$databases[$default_database]->_dsn['scheme'];
        return self::$backends[$db];
    }

    public static function getDefaultDatabaseName() {
        return empty(self::$backend_global['default_database']) ? 'default' : self::$backend_global['default_database'];
    }

    public static function getDatabase($name=null) {
        if ($name === null) {
            $name = self::getDefaultDatabaseName();
        }
        if (isset(self::$databases[$name])) {
            return self::$databases[$name];
        }
        throw new Exception(sprintf('Database "%s" does not exist', $name));
    }

    public function startup() {
        foreach (self::$databases as $database) {
            $database->startup();
        }
    }

    public function shutdown() {
        foreach (self::$databases as $database) {
            $database->shutdown();
        }
    }

    public static function parse_dsn($dsn) {
        $parts = parse_url($dsn);
        $names = array('dsn', 'scheme', 'host', 'port', 'user', 'pass', 'path', 'query', 'fragment');
        foreach ($names as $name) {
            if (!isset($parts[$name])) {
                $parts[$name] = null;
            }
        }
        if (count($parts) == 0 || !isset($parts['scheme'])) {
            throw new Exception('Empty data source name');
        }
        switch ($parts['scheme']) {
            case 'sqlite':
            case 'sqlite2':
            case 'sqlite3':
                if (isset($parts['host']) && $parts['host'] == ':memory') {
                    $parts['database'] = ':memory:';
                    $parts['dsn'] = 'sqlite::memory:';
                } else {
                    $parts['database'] = $parts['path'];
                    $parts['dsn'] = $parts['scheme'] . ':' . $parts['path'];
                }
                break;
            case 'mysql':
            case 'mssql':
            case 'firebird':
            case 'pgsql':
            case 'odbc':
            case 'oracle':
            case 'mongo':
                if (!isset($parts['path']) || $parts['path'] == '/')
                    $parts['path'] = null;
                if (isset($parts['path']))
                    $parts['database'] = substr($parts['path'], 1);
                if (!isset($parts['host']))
                    throw new Exception('No hostname set in data source name');
                $parts['dsn'] = $parts['scheme'] . ':host='
                        . $parts['host'] . ';dbname='
                        . $parts['database'];
                if (isset($parts['port']))
                    $parts['dsn'] .= ';port=' . $parts['port'];
                break;
            default:
                throw new Exception('Unknown driver ' . $parts['scheme']);
        }
        return $parts;
    }

}

?>