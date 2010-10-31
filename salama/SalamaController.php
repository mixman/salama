<?php

class SalamaController extends SalamaBase implements ArrayAccess, IteratorAggregate, Countable {
    var $_row = array();
    var $_query = null;

    public function __get($key) {
        if (isset(SalamaData::$c[$key])) {
            // $model->RelatedModel (disallowed for now)
            throw new Exception("Unknown relation");
        } elseif ($this->isRelation($key)) {
            // $model->relation
            if (!$model = $this->hasRelationData($this->getQuery()->model, $key)) {
                throw new Exception("No relation found for $model AND {$this->getQuery()->model}->$key");
            }
            $this->evaluateLazyCall($this->getQuery());
            $pk = $this->getPk();

            $salama = $this->getModelInstance($model);
            SalamaQuery::add($salama, $model, $key);

            $map = SalamaRelation::getMap($this->getQuery()->model, $key, $salama->getQuery()->model);
            $salama = $salama->where(q::$map['localkey']($this->$map['foreignkey']));
        } else {
            // $model->field
            $query = $this->getQuery();
            if (!isset(SalamaData::$c[$query->model][$key])) {
                throw new Exception("Invalid model field/relation {$query->model}->$key");
            }
            $this->evaluateLazyCall($query);

            return $query->getItem($key);
        }

        return $salama;
    }

    public function __set($key, $value) {
        $this->evaluateLazyCall($this->getQuery());
        if (isset(SalamaData::$c[$this->getQuery()->model][$key])) {
            $this->getQuery()->setItem($key, $value);
        } else {
            throw new Exception("Invalid model field value {$this->getQuery()->model}->$key");
        }
    }

    public function __call($method, $args) {
        $salama = $this;
        // temporary shortcuts while shaping functionality: raw, create, all
        if ($method == 'raw') {
            $model = 'User';
            SalamaQuery::add($this, $model, null);
            $salama = $this->getModelInstance($model);
        }
        if ($method == 'create') {
            return $salama;
        }
        if ($method == 'all') {
            return $this->call($this->getQuery());
        }

        if (!$salama->getQuery()->builder->isValidMethod($method)) {
            throw new Exception("Unknown Query method $method()");
        }
        $salama->getQuery()->methods[] = array($method, $args);
        return $salama;
    }

    public function evaluateLazyCall($query) {
        if ($query) {
            if ($query->methods && !$query->executed) {
                $this->launchQueries($query);
            }
        }
    }

    public function isRelation($alias) {
        if (!isset(SalamaData::$c[$this->getQuery()->model][$alias])) {
            return false;
        }
        # hasOne/hasMany/belongsTo?
        if (SalamaBuild::isRelation($this->getQuery()->model, $alias)) {
            return true;
        }
        return false;
    }

    public function hasRelationData($table, $property) {
        $relations = array('belongsto', 'hasone', 'hasmany');
        foreach ($relations as $k => $relation) {
            $model = isset(SalamaData::$c[$table][$property][$relation]) ?
                    SalamaData::$c[$table][$property][$relation] : null;
            if ($model)
                break;
        }
        return $model;
    }

    public function fireSignal($type, $table, $query) {
        $instance = $this->getModelInstance($table);
        $signal = call_user_func_array(array($instance, $type . $query->_query_type), array());
        return $signal;
    }

    public function launchQueries($query) {
        if ($query->executed) {
            return;
        }
        if ($query->parent) {
            # relational query
            $this->getRelation()->queryRelation($this, $query);
        } else {
            # base query
            $this->call($query);
        }
    }

    # TEMPORARY: execute raw() query
    public function goraw() {
        if (!$this->getQuery()) {
            $model = 'User';
            SalamaQuery::add($this, $model, null);
            $salama = $this->getModelInstance($model);
        }
        $query = $this->getQuery();
        $query->builder->prepare($query);
        $items = $query->database->execute($query, $this);
        return $this;
    }

    # execute Salama query
    public function call($query) {
        $table = $query->model;

        # SIGNALS: pre*
        $signal_result = $this->fireSignal('pre', $table, $query);

        $query = $this->warpspeed($query, $this);

        # SIGNALS: post*
        $signal_result = $this->fireSignal('post', $table, $query);

        return $this;
    }

    public function warpspeed($query, $instance) {
        // @TODO choose correct backend-database
        $db_class = new SalamaSqlDatabase(0, 0);
        return $db_class->warpspeed($query, $instance);
    }

    #
    # methods that evaluate the Query immediately
    #

	public function update($values=array()) {
        foreach ($values as $k => $v) {
            $this->getQuery()->setItem($k, $v);
        }
        return $this->save(SalamaQuery::$UPDATE);
    }

    public function delete() {
        $this->getQuery()->_query_type = SalamaQuery::$DELETE;
        $this->call($this->getQuery());
    }

    public function save($mode='insert') {
        $query = $this->getQuery();
        $query->_query_type = $mode;

        $pk = SalamaBuild::getPk($query->model);
        if ($mode == SalamaQuery::$INSERT) {
            $rows = $this->_row;
            foreach ($rows as $row) {
                $query = $row->getQuery();
                $query->_query_type = $mode;
                $this->call($query);
            }
            // initial insert
            if (empty($rows)) {
                $this->call($query);
            }
        } else {
            $this->call($query);
        }

        // @TODO boolean of all queries
        return $query->_code;
    }

    // SPL IteratorAggregate: getIterator
    public function getIterator() {
        $query = $this->getQuery();
        $this->evaluateLazyCall($query);
        if (!$query->executed) {
            throw new Exception("Salama is not iterable: no Query");
        }
        return new ArrayIterator($this->_row);
    }

    // SPL ArrayAccess: offsetSet, offsetGet, offsetUnset, offsetExists
    function offsetSet($offset, $value) {
        $query = $this->getQuery();
        $this->evaluateLazyCall($query);
        if (!$query->executed) {
            throw new Exception("Model does not support item assignment");
        }
    }

    function offsetGet($offset) {
        $query = $this->getQuery();
        $this->evaluateLazyCall($query);
        if (!$query->executed) {
            throw new Exception("Model does not support item assignment");
        }
        return $this->_row[$offset];
    }

    function offsetUnset($offset) {
        unset($this->_row[$offset]);
    }

    function offsetExists($offset) {
        return isset($this->_row[$offset]);
    }

    // SPL Countable
    public function count() {
        $this->evaluateLazyCall($this->getQuery());
        return count($this->_row);
    }

}

?>