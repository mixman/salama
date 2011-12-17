<?php

/**
 * ACTIONS
 * =======
 *
 * build:
 * prepare models.php cache
 *
 * syncdb:
 * insert models.php information into database
 *
 */
class SalamaRunner {
    public function run($action) {
        switch ($action) {
            case 'build':
                $this->build();
                break;
            case 'syncdb':
                $this->syncdb();
                break;
            case 'test':
                $this->test();
                break;
            default:
                throw new Exception("no action specified for: {$action}");
        }
    }

    function build() {
        $b = new SalamaBuild();
        $classes = $b->getModelnames();
        foreach ($classes as $c) {
            $b->genTable($c);
        }
        # [create] has belongsTo fields as they are fields
        # - just no data until processContraints runs.
        # Model meta run
        # - set correct table names and aliases
        $b->setTableNames();
        # belongsTo,hasOne,hasMany[through]
        $b->processConstraints();
        # save output
        $env = Salama::$_settings['env'];
        $cache = Salama::$_settings['cache'];
        $data = $b->settingsAll;
        # create SalamaData class with build information accessible statically
        $sd = $data;
        # keep relation data separate from Table (fields)
        $rel = $sd[SalamaBuild::$meta];
        unset($sd[SalamaBuild::$meta]);
        $data_php = var_export($sd, true);
        $rel_php = var_export($rel, true);
        $sd = '<?php class SalamaData { public static $c = DATA_HOLDER; public static $rel = REL_HOLDER; } ?>';
        $sd = str_replace('DATA_HOLDER', $data_php, $sd);
        $sd = str_replace('REL_HOLDER', $rel_php, $sd);
        file_put_contents($cache . "/SalamaData_{$env}.php", $sd);
        file_put_contents($cache . "/build_cache_{$env}.php", serialize($data));
    }

    function syncdb() {
        # note: same as build without write
        # - separate build to build+build-write ?
        $b = new SalamaBuild();
        $classes = $b->getModelnames();
        foreach ($classes as $c) {
            $b->genTable($c);
        }
        $b->processConstraints();
        $b->setTableNames();
        $r = $b->createTable();
        $sql = implode("\n", $r);
        # databases in use
        # @TODO cleanup. duplicate code from SalamaSuite.php
        $xml = simplexml_load_string(file_get_contents(Salama::$_settings['config'] . '/databases.xml'));
        $env = Salama::$_settings['env'];
        $res = $xml->xpath("//configuration[@environment='{$env}']/database");
        $dbInUse = array();
        foreach ($res as $row) {
            $dsn = trim($row->dsn);
            $parts = explode('/', $dsn);
            $db = array_pop($parts);
            $dbInUse[] = $db;
        }
        # [/END-NON-DRY]
        Model::raw($sql)->goraw();
    }

}

?>