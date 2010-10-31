<?php

class SalamaBase {

    public static $_instance = array();
    public static $_instance_model = array();
    public static $_manager = null;

    public function getQuery($query_id=null) {
        $query = $this->_query;
        if (!$query) {
            $query = SalamaQuery::add($this, get_class($this));
        }
        return $query;
    }

    public function getManager() {
        if (!isset(self::$_manager)) {
            self::$_manager = new SalamaDatabaseManager();
        }
        return self::$_manager;
    }

    public function getRelation() {
        return new SalamaRelation();
    }

    public function _prepare($model, $class, $null_fields_for_use=false) {
        unset($model->_meta);
        unset($model->_meta_model);

        foreach (SalamaData::$c[$class] as $k => $v) {
            if (!in_array($k, SalamaData::$c[$class][SalamaBuild::$meta]['fields'])) {
                unset($model->$k);
            }
            if ($null_fields_for_use) {
                $model->$k = null;
            } else {
                unset($model->$k);
            }
        }

        $model->_query = $this->_query;
        return $model;
    }

    public function getModelInstance($class, $null_fields_for_use=false) {
        $model = new $class();
        $model = $this->_prepare($model, $class, $null_fields_for_use);
        return $model;
    }

    public function __toString() {
        return get_class($this) . ': ' . $this->getPk();
    }

}

?>