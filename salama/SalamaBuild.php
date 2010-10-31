<?php

# Model -> SQL mapper
class SalamaBuild {
    public $settings = array();
    public $settingsAll = array();
    public static $meta = '__meta';
    public static $meta_model = '_meta_model';
    private $primaryKey = 'primarykey';
    private $relations = array('hasone', 'hasmany');
    private $log = array();
    public $fields = array(
        'CharField' => array('field' => 'VARCHAR'),
        'URLField' => array('field' => 'VARCHAR', 'length' => 200),
        'EmailField' => array('field' => 'VARCHAR', 'length' => 75),
        'ImageField' => array('field' => 'VARCHAR', 'length' => 100),
        'DateField' => array('field' => 'DATE'),
        'IntegerField' => array('field' => 'INT', 'length' => 11),
        'TextField' => array('field' => 'TEXT'),
        'BooleanField' => array('field' => 'bool')
    );

    /**
     * Process a single field
     * @param string fieldName
     * @param strng fieldSettings
     */
    public function processVars($fieldName, $settings) {
        $settings = $this->parseSettings($settings);
        $this->settingsAll[$this->table][$fieldName] = $settings;
        if (isset($settings['type']) && isset($this->fields[$settings['type']])) {
            $fieldInfo = $this->fields[$settings['type']];
        } else {
            $fieldInfo = null;
        }
        $fieldtype = $fieldInfo['field'];
        # length?
        $maxLength = null;
        $maxLengthSql = '';
        if (isset($settings['maxlength'])) {
            $maxLength = $settings['maxlength'];
        } else {
            if (isset($fieldInfo['length'])) {
                $maxLength = $fieldInfo['length'];
            }
        }
        if ($maxLength) {
            $maxLengthSql = '(' . $maxLength . ')';
        }
        # null?
        $null = 'NOT NULL';
        if (isset($settings['null'])) {
            if ($settings['null'] == 'true') {
                $null = 'NULL';
            }
        }
        # primaryKey?
        $primaryKey = '';
        if (isset($settings['primarykey'])) {
            $primaryKey = 'PRIMARY KEY';

            if (stripos($settings['type'], 'Integer') === false) {
                throw new SalamaBuildException("PrimaryKey must be of type Integer");
            }
        }
        # auto_increment?
        $autoIncrement = '';
        if (isset($settings['autoincrement'])) {
            $autoIncrement = 'AUTO_INCREMENT';
        }
        # custom db_column? use for SQL only
        $fieldNameSql = $fieldName;
        if (isset($settings['db_column'])) {
            $fieldNameSql = $settings['db_column'];
        }
        # prepare SQL string
        $sql = "`{$fieldNameSql}` {$fieldtype}{$maxLengthSql} {$autoIncrement} {$null} {$primaryKey},";
        # no type set? don't print on this run. possibly a constraint.
        if (!isset($settings['type'])) {
            $sql = '';
        }
        # relations?
        $relationType = '';
        foreach ($this->relations as $k => $relation) {
            if (isset($settings[$relation])) {
                $relationType = $settings[$relation];
            }
        }
        $values = array(
            'maxLength' => $maxLength,
            'null' => $null,
            'field' => $fieldtype,
            'name' => $fieldName,
            'primaryKey' => $primaryKey,
            'autoIncrement' => $autoIncrement,
            'relation' => $relationType
        );
        return $res = array('val' => $values, 'sql' => $sql);
    }

    public function processPks() {
        
    }

    /**
     * Process constraints for tables
     */
    public function processConstraints() {
        foreach ($this->settingsAll as $table => $settings) {
            foreach ($settings as $name => $values) {
                if ($name == self::$meta) {
                    continue;
                }
                foreach ($values as $k => $v) {
                    $sql = array();
                    # belongsTo (=foreign key)
                    if ($k == 'belongsto') {
                        $fkName = $name;
                        # apply referenced PK/index settings for FK
                        # @TODO currently assuming always referencing PK, could be INDEX!
                        # - if referenceKey is set, and it doesnt refer to PK, do an INDEX + use the value
                        $pkName = $this->settingsAll[$v][self::$meta][$this->primaryKey]['name'];
                        $settingsFromPK = $this->settingsAll[$v][$pkName];
                        unset($settingsFromPK['primarykey']);
                        unset($settingsFromPK['autoincrement']);
                        # we've got referenced table PK data here
                        # - means fieldName is wrong.
                        # - fix: add these settings for our belongsTo field, and process that
                        $finalSettings = array_merge($values, $settingsFromPK);
                        # do not add belongsTo column to its targetTable as a column
                        #$this->settingsAll[$v][$fkName] = $finalSettings;
                        $res = $this->processVars($fkName, $finalSettings);
                        $alter = "ALTER TABLE `" . $this->getCustomTableName($table) . "`";
                        # add index
                        $sql[] = "{$alter} ADD INDEX (`{$fkName}`);";
                        # create column first, then add FK constraint
                        if (!empty($res['val']['maxLength'])) {
                            $maxLengthSql = '(' . $res['val']['maxLength'] . ')';
                        }
                        # onUpdate? onDelete? more than one?
                        $extra = '';
                        if (isset($values['ondelete'])) {
                            $extra.='ON DELETE ' . strtoupper($values['ondelete']);
                        }
                        if (isset($values['onupdate'])) {
                            $extra.='ON UPDATE ' . strtoupper($values['onupdate']);
                        }
                        $sql[] = "{$alter} ADD FOREIGN KEY (`{$fkName}`) REFERENCES `" . $this->getCustomTableName($v) . "` (`{$pkName}`) {$extra};";
                        # save FK info to global __meta
                        $this->settingsAll[self::$meta]['rel'][$table][$v] = array(
                            'local' => $fkName,
                            'ref' => $pkName
                        );
                        # save FK info to table's __meta
                        $this->settingsAll[$table][self::$meta]['fks'][$fkName] =
                                array($v => array(
                                        'local' => $fkName,
                                        'ref' => $pkName
                                    )
                        );
                        # save relation SQL to global __meta
                        $this->log(implode("\n", $sql));
                        $this->settingsAll[self::$meta]['rel_sql'][$table][] = $sql;
                        $this->unsetFromCreate($table, $fkName);
                        $this->settingsAll[$table][self::$meta]['create'][] = $res;
                    }
                    if ($k == 'hasmany') {
                        $this->settingsAll[self::$meta]['hasmany'][$v][$table][] = $name;
                        $this->unsetFromCreate($table, $name);
                    }
                    if ($k == 'hasone') {
                        $this->settingsAll[self::$meta]['hasone'][$v][$table][] = $name;
                        $this->unsetFromCreate($table, $name);
                    }
                    if ($k == 'through') {
                        $this->settingsAll[self::$meta]['through'][$v][$table][] = $name;
                        #$this->unsetFromCreate($table, $name);
                    }
                } // endforeach($values as $k=>$v)
            } // endforeach($settings as $name=>$values)
        } // endforeach($this->settingsAll as $table=>$settings)
    }

    public function unsetFromCreate($table, $fkName) {
        foreach ($this->settingsAll[$table][self::$meta]['create'] as $ck => $cv) {
            if ($cv['val']['name'] == $fkName) {
                unset($this->settingsAll[$table][self::$meta]['create'][$ck]);
            }
        }
    }

    /**
     * Generate Table SQL for a Model
     * @param string tableName
     */
    public function genTable($table) {
        $this->table = $table;
        $my_class = new $table();
        $class_vars = get_class_vars(get_class($my_class));
        # remove everything that begins with _, that is not _meta or _model_meta
        # - these values creep in from SalamaAccessTable that Model extends
        $ok = array('_meta', self::$meta_model);
        foreach ($class_vars as $k => $v) {
            if (substr($k, 0, 1) == '_') {
                if (!in_array($k, $ok)) {
                    unset($class_vars[$k]);
                }
            }
        }
        $fields = array();
        # PROCESS FIELD VARS
        foreach ($class_vars as $k => $v) {
            # @TODO process _meta properly
            if ($k == '_meta') {
                $metaSettings = $this->parseSettings($v);
                if (isset($metaSettings['tablename'])) {
                    $this->settingsAll[$this->table][self::$meta]['tablename'] = $metaSettings['tablename'];
                }
            }
            # do not include _meta_model outside Model
            if ($k == '_meta' || ($k == self::$meta_model && $table != 'Model')) {
                continue;
            }
            $res = $this->processVars($k, $v);
            $fields[] = $res;
        }

        # PROCESS PK
        # check if primaryKey was defined in Model
        $hasCustomPk = false;
        foreach ($this->settingsAll[$this->table] as $k => $v) {
            foreach ($v as $k2 => $v2) {
                if (stripos($k2, 'primarykey') !== false) {
                    $hasCustomPk = true;
                    $pk = $v;
                    $pkName = $k;
                }
            }
        }
        $this->log("PK status: (int) " . intval($hasCustomPk) . " hasCustomPk: " . $hasCustomPk);
        if ($hasCustomPk) {
            $res = $this->processVars($pkName, $pk);
            $sql = $res['sql'];
        } else {
            $sql = "`id` int(11) AUTO_INCREMENT NOT NULL PRIMARY KEY,";
            $pk = 'type=IntegerField,maxLength=11,primaryKey=true,autoIncrement=true';
            $pkName = 'id';
            $this->log($sql);
        }
        // - save pk
        $this->settingsAll[$this->table][self::$meta][$this->primaryKey]['custom'] = intval($hasCustomPk);
        $this->settingsAll[$this->table][self::$meta][$this->primaryKey]['sql'] = $sql;
        $settings = $this->processVars($pkName, $pk);
        $this->settingsAll[$this->table][self::$meta][$this->primaryKey]['settings'] = $settings;
        $this->settingsAll[$this->table][self::$meta][$this->primaryKey]['name'] = $pkName;
        if (!$hasCustomPk) {
            $this->settingsAll[$this->table][self::$meta]['create'][] = $settings;
        }
        // - save fields
        foreach ($fields as $the => $field) {
            $this->settingsAll[$this->table][self::$meta]['create'][] = $field;
        }
        # field names container
        foreach ($this->settingsAll[$this->table][self::$meta]['create'] as $k => $v) {
            # purely field names (includes belongsTo)
            if (empty($v['val']['relation']) && !in_array($v['val']['name'], $ok)) {
                $this->settingsAll[$this->table][self::$meta]['fields'][] = $v['val']['name'];
            }
        }
        # relations names container
        foreach ($this->settingsAll[$this->table][self::$meta]['create'] as $k => $v) {
            # hasOne, hasMany
            if (!empty($v['val']['relation']) && !in_array($v['val']['name'], $ok)) {
                $this->settingsAll[$this->table][self::$meta]['relations'][] = $v['val']['name'];
            }
        }
    }

    /**
     * Parse Model field strings
     * @param string settings
     * @return array settings
     */
    public function parseSettings($settings) {
        # already array?
        if (!is_string($settings)) {
            return $settings;
        }
        $parts = explode(',', trim($settings));
        $s = array();
        foreach ($parts as $k => $v) {
            $kv = explode('=', trim($v));
            # convert all key values to lowercase to ease against simple typos
            $key = strtolower(trim($kv[0]));
            $s[$key] = trim($kv[1]);
        }
        return $s;
    }

    /**
     * Set the real name of the table, as in schema, and the default alias used in queries
     */
    public function setTableNames() {
        $caseFormat = $this->settingsAll['Model'][self::$meta_model]['tablecaseformat'];
        $aliasFormat = $this->settingsAll['Model'][self::$meta_model]['tablealiasformat'];
        foreach ($this->settingsAll as $k => $v) {
            # do not process __meta
            if (in_array($k, array(self::$meta))) {
                continue;
            }
            $tableName = $this->getRealTableName($caseFormat, $k);
            # settingsAll.k.this->meta.tablename
            # - genTable responsible for having tableName set
            $custom_set = null;
            if (isset($this->settingsAll[$k][self::$meta]['tablename'])) {
                $custom_set = $this->settingsAll[$k][self::$meta]['tablename'];
            }
            $this->log("setTableNames / style: {$caseFormat} / k: {$k} / tableName: {$tableName} / custom-set: " . $custom_set);
            # set correct tablename as defined in schema, but do not override individual defaults
            if (!isset($this->settingsAll[$k][self::$meta]['tablename'])) {
                $this->settingsAll[$k][self::$meta]['tablename'] = $tableName;
            }
            # ALIAS
            $this->settingsAll[$k][self::$meta]['alias'] = $this->getTableAlias($aliasFormat, $k);
        }
    }

    /**
     * Convert Table class names to conform to those used in the database
     * @param string CaseType
     * @param string TableClassName
     * @return string TableName 
     * 
     * Cases (eg. ForumThread):
     * UpperCamelCase - ForumThread
     * LowerCamelCase - forumThread
     * LowerStrikeCase - forum_thread
     * UpperStrikeCase - Forum_Thread
     * LowerCase - forumthread
     */
    public function getRealTableName($case, $table) {
        switch ($case) {
            case 'UpperCamelCase';
                $real = $table;
                break;
            case 'LowerCamelCase';
                $real = strtolower($table[0]) . substr($table, 1);
                break;
            case 'LowerStrikeCase';
                $words = $this->unCamelCase($table);
                foreach ($words as $k => $v) {
                    $words[$k] = strtolower($v);
                }
                $real = implode('_', $words);
                break;
            case 'UpperStrikeCase';
                $real = implode('_', $this->unCamelCase($table));
                break;
            case 'LowerCase':
                $real = strtolower($table);
                break;
            default:
                throw new SalamaBuildException('_meta value tableCaseFormat not set in Model');
        }
        return $real;
    }

    # create Table aliases based on given rule

    public function getTableAlias($case, $table) {
        switch ($case) {
            # HelloWorld => hw
            case 'InitialLowerCase';
                $words = $this->unCamelCase($table);
                foreach ($words as $k => $v) {
                    $words[$k] = strtolower($v[0]);
                }
                $alias = implode('', $words);
                break;
            # HelloWorld => h
            case 'InitialLower';
                $alias = strtolower($table[0]);
                break;
            default:
                throw new SalamaBuildException('_meta value tableAliasFormat not set in Model');
        }
        return $alias;
    }

    # split camel cased words by their caps
    # http://fi.php.net/manual/en/function.ucwords.php#49303

    function unCamelCase($str) {
        # if lowercase first char, skip unCamelCasing (as there are none). return str in array.
        if ($str[0] !== ucfirst($str[0])) {
            return array($str);
        }
        $bits = preg_split('/([A-Z])/', $str, false, PREG_SPLIT_DELIM_CAPTURE);
        $a = array();
        array_shift($bits);
        for ($i = 0; $i < count($bits); ++$i) {
            if ($i % 2) {
                $a[] = $bits[$i - 1] . $bits[$i];
            }
        }
        return $a;
    }

    public function createTable() {
        $tables = $this->settingsAll;
        unset($tables['Model']);
        unset($tables[self::$meta]);
        $s[] = "BEGIN;";
        foreach ($tables as $tbl => $data) {
            $s[] = "CREATE TABLE IF NOT EXISTS `" . $this->getCustomTableName($tbl) . "` (";
            foreach ($data[self::$meta]['create'] as $tk => $tv) {
                $s[] = " " . $tv['sql'];
            }
            # remove trailing comma
            $key = count($s) - 1;
            $s[$key] = trim($s[$key], ",");
            $s[] = ");";
        }
        # constraints
        foreach ($this->settingsAll[self::$meta]['rel_sql'] as $tbl => $data) {
            foreach ($data as $r => $more_data) {
                foreach ($more_data as $rk => $rv) {
                    $s[] = $rv;
                }
            }
        }
        #@TODO indexes
        $s[] = "COMMIT;";
        return $s;
    }

    # check for a custom tableName
    # @FIXME dupe of SalamaQuery->getRealTableName

    public function getCustomTableName($tbl) {
        return isset($this->settingsAll[$tbl][self::$meta]['tablename']) ? $this->settingsAll[$tbl][self::$meta]['tablename'] : null;
    }

    public function run() {
        
    }

    /**
     * Get list of model names
     * @param string path to models dot php
     * @return array names of models
     */
    public function getModelNames() {
        $modelstr = file_get_contents(Salama::$_settings['config'] . '/models.php');
        # remove docblock comments
        preg_match_all("/\/\*.*?\*\//is", $modelstr, $out, PREG_PATTERN_ORDER);
        foreach ($out as $k => $v) {
            $modelstr = str_replace($v, '', $modelstr);
        }
        # remove hash comments
        preg_match_all("/#.*/i", $modelstr, $out, PREG_PATTERN_ORDER);
        foreach ($out as $k => $v) {
            $modelstr = str_replace($v, '', $modelstr);
        }
        # remove slashslash comments
        preg_match_all("/\/\/.*/i", $modelstr, $out, PREG_PATTERN_ORDER);
        $modelstr = str_replace($v, '', $modelstr);
        # grab model names
        preg_match_all("/class (.*?) /", $modelstr, $out, PREG_PATTERN_ORDER);
        return $out[1];
    }

    public function log($msg) {
        $this->log[] = $msg;
    }

    public function printLog() {
        return implode("\n", $this->log);
    }

    ## ACCESSORS ##

    public static function getPk($table) {
        return SalamaData::$c[$table][self::$meta]['primarykey']['name'];
    }

    public static function getAlias($table) {
        return SalamaData::$c[$table][self::$meta]['alias'];
    }

    public static function getRelation($relation, $table, $case) {
        if ($case == 'ref') {
            return isset(SalamaData::$rel['rel'][$relation][$table]['ref']) ? SalamaData::$rel['rel'][$relation][$table]['ref'] : null;
        } elseif ($case == 'local') {
            return isset(SalamaData::$rel['rel'][$relation][$table]['local']) ? SalamaData::$rel['rel'][$relation][$table]['local'] : null;
        } elseif ($case == 'both') {
            return array(SalamaData::$rel['rel'][$relation][$table]['ref'], SalamaData::$rel['rel'][$relation][$table]['local']);
        } else {
            throw new SalamaBuildException("bad params");
        }
    }

    public static function isRelation($table, $field) {
        $relation_candidate = isset(SalamaData::$c[$table][$field]) ? SalamaData::$c[$table][$field] : null;
        return (isset($relation_candidate) && !in_array($field, SalamaData::$c[$table][SalamaBuild::$meta]['fields']));
    }

    # @TODO build _chain
    # get full model name for a relation alias

    public static function getModelForRelation($relation) {
        #
    }

}

?>