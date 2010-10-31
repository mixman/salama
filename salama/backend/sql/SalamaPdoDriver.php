<?php

class SalamaPdoDriver {
    var $dsn = null;
    public function connect($use_database=true) {
        $dsn = $this->dsn;
        # @TODO set from databases.xml settings
        $db_opts = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => true
        );
        try {
            return new PDO($dsn['dsn'], $dsn['user'], $dsn['pass'], $db_opts);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function shutdown() {
        self::$_connection = null;
    }

}

?>