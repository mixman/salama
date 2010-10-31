<?php

class SalamaJob {
    public $name = null;
    public $params = array();
    public $pdo_param = array();
    public $sql = '';
    public $objs = array();

    public $jobs = array();
    public $builder = null;

    public function __construct($query) {
        $this->builder = $query->builder;
    }

    public function createJob($name, $query) {
        if(!isset($this->jobs[$name])) {
            $this->jobs[$name] = $query->jobs(true);
            $this->jobs[$name]->name = $name;
        }
        return $this->jobs[$name];
    }

    public function getJob($name) {
        return isset($this->jobs[$name]) ? $this->jobs[$name] : null;
    }

    public function getJobs() {
        return $this->jobs;
    }

    # ->join(Model) ... join Model against from()
    # ->join(Model, array('model'=>Model2)) ... join between specified tables
    # ->join(Model, array('model'=>Model2, 'keys'=>array(Model::$field, Model2::$field2), 'type'=>'right')
    function setupJoin($args, $query) {
        if(count($args) == 1) {
            $from = $query->model;
        } else {
            $from = $args[1];
        }
        $from_alias = $this->builder->getAlias($from, $query);
        $type = 'LEFT';
        foreach($args as $key=>$model) {
            $model_alias = $this->builder->getAlias($model, $query);
            $map = SalamaRelation::getMap($model, $from);
            $model_fk = $map['foreignkey'];
            $from_pk = SalamaBuild::getPk($from);
            $result[] = "{$type} JOIN {$model} {$model_alias} ON {$from_alias}.{$from_pk}={$model_alias}.{$model_fk}";
            $query->_involved_tables[] = array('table'=>$model, 'alias'=>$model_alias);
        }
        return $result;
    }


    ########################################################################
    ## JOBS ##
    ########################################################################

    public function select($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
    public function update($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
    public function insert($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
    public function delete($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
    public function set($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
    public function from($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
    public function where($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
    public function limit($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
    public function groupby($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
    public function order($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
    public function raw($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
    public function join($args, $query) {
        throw new SalamaJobNotImplementedException();
    }
}

?>