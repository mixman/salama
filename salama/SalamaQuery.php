<?php

class SalamaQuery extends SalamaControllerSwitch {
    public static $INSERT = 'insert';
    public static $SELECT = 'select';
    public static $UPDATE = 'update';
    public static $DELETE = 'delete';

    var $model = null;
    var $model_relation = null;
    var $parent = null;
    var $methods = array();
    var $items = array();
    var $get = null;
    var $query_id_parent = null;
    var $state = null; // @TODO clean (new?), dirty (modified), processed (saved)
    var $connection_id = null; // @TODO $this->getConnection()
    var $iterator = null;
    var $_dirty = array();
    var $executed = false;
    var $_involved_tables = array();
    var $builder = null;
    var $database = null;
    var $jobs = null;

    # from Controller
    public $_query_type = 'select';

    # from database
    public $_code = null;
    public $_last_insert_id;

    public function __construct($model, $model_relation, $salama) {
        $this->model = $model;
        $this->model_relation = $model_relation;
        // manager holds connections, and the builder; or does the connection hold the builder?
        $this->builder = $salama->getManager()->getDatabase()->getBuilder();
        $this->database = $salama->getManager()->getDatabase();
        $this->jobs();
    }

    public static function add($salama, $model, $model_relation=null) {
        $salama->_query = new SalamaQuery($model, $model_relation, $salama);
        return $salama->_query;
    }

    public function jobs($new=false) {
        if($new) {
            $name = get_class($this->database->getJobClass($this));
            return new $name($this);
        }

        if(!isset($this->jobs)) {
            $this->jobs = $this->database->getJobClass($this);
        }
        return $this->jobs;
    }

    public function is($model) { 
        return ($this->model == $model);
    }

	public function findTableByAlias($alias) {
		foreach($this->_involved_tables as $k=>$v) {
			if($v['alias'] == $alias) {
				# return table, or its relational-name as defined by hasOne/...
                $table = isset($v['rel']) ? $v['rel'] : $v['table'];
                break;
			}
		}
		return $table;
	}

	/**
	 * Hydrate column results to their corresponding Table
	 * eg. u__id, uc__posted => User array(id), UserComment array(posted)
	 * @param array data
	 * @param string tableAlias of a Table
	 * @param bool strict?
	 * @return array
	 */
	public function hydrateResult($data, $tableAlias, $strict=true) {
		# Hydrate result based on alias=>table mapping stored in ::_table
		# - result ($data) can be 0, 1 or many rows
		$result = array();
		foreach($data as $k=>$field) {
			$tmp = array();
			$alias = '__';
			foreach($field as $name=>$value) {
                # @BUG first lookup is __.__ ?
				$marker = $alias.'__';
				if(strpos($name, $marker) !== false) {
					$realFieldName = substr($name, strlen($marker));
				} else {
					list($alias, $realFieldName) = explode('__', $name);
					$table = $this->findTableByAlias($alias); # small optimization w/table var
				}
				# save only fields that belong to Table?
				if($strict) {
					if($tableAlias == $alias) $tmp[$realFieldName] = $value;
				} else {
					$tmp[$realFieldName] = $value;
				}
			} // endforeach
			$tableAliasTable = $table;
			if($strict) {
                $tableAliasTable = $this->findTableByAlias($tableAlias);
            }
			$result[$tableAliasTable][$k] = $tmp;
		} // endforeach
		return $result;
	}

    ################################
    # modified model fields accessors
    ################################

	public function setDirty($value) {
        if(!$this->isDirty($value)) {
            $this->_dirty[] = $value;
        }
    }

	public function setUnmodified($value) {
        $key = array_search($value, $this->_dirty);
        if($key) {
            unset($this->_dirty[$key]);
        }
    }

    public function clearDirty() {
        $this->_dirty = array();
    }

	public function isDirty($value) {
        return in_array($value, $this->_dirty);
    }

	public function getDirty($value) {
        $key = array_search($value, $this->_dirty);
        if($key) {
            return $this->_dirty[$key];
        } else {
            return null;
        }
    }

	public function getAllDirty() {
        return $this->_dirty;
    }
}

?>