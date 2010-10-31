<?php

class SalamaSqlDatabase implements SalamaDatabase {
    public $_dsn = null;
    public static $_connection = array();
    public $_name = null;

    public function __construct($name, $dsn) {
        $this->_name = $name;
        $this->_dsn = $dsn;
    }

    public function getBuilder() {
        $backend = SalamaDatabaseManager::getCurrentBackend();
        $builder_name = sprintf("Salama%sBuilder", ucfirst($backend['database']));
        $builder = new $builder_name();
        return $builder;
    }

    public function getJobClass($query) {
        $backend = SalamaDatabaseManager::getCurrentBackend();
        $builder_name = sprintf("Salama%sJob", ucfirst($backend['database']));
        $builder = new $builder_name($query);
        return $builder;
    }

	public function getConnection() {
		if(!isset(SalamaSqlDatabase::$_connection[$this->_name])) {
            $backend = SalamaDatabaseManager::getCurrentBackend();
            $driver_name = sprintf("Salama%sDriver", ucfirst($backend['driver']));
            $driver = new $driver_name();
            $driver->dsn = $this->_dsn;
            $connection = $driver->connect();
            SalamaSqlDatabase::$_connection[$this->_name] = $connection;
        }
		return SalamaSqlDatabase::$_connection[$this->_name];
	}

	public function execute($query, $instance) {
        $manager = $instance->getManager();
        $database = $manager->getDatabase();
		# fire up PDO with or without selected database
		# - check for: ALTER/CREATE/DROP DATABASE, SHOW *,
		if( preg_match('/^([a-zA-Z]*?) DATABASE/', $query->builder->sql)
			|| preg_match('/^SHOW/', $query->builder->sql)) {
			$database->getConnection(false);
		} else {
			$database->getConnection();
		}
		$result = null;

		try {
			$stmt = $database->getConnection()->prepare($query->builder->sql);
			$c = 1;
            $currently_querying = sprintf("sql_%s", $query->_query_type);
            $builder_class = $this->getBuilder();
            $job_class = $query->jobs;
            foreach($builder_class::$$currently_querying as $name=>$data) {
                if($job = $job_class->getJob($name)) {
                    foreach($job->params as $k=>$params) {
                        foreach((array)$params as $param) {
                            $pdo_bind_key = intval($c);
                            $pdo_hint = isset($job->pdo_param[$k]) ? $job->pdo_param[$k] : PDO::PARAM_STR;
                            $stmt->bindValue($pdo_bind_key, $param, $pdo_hint);
                            $c++;
                        }
                    }
                }
            }

			$query->_code = $stmt->execute();

            if($query->_code === false) {
				throw new SalamaQueryException(sprintf("Query failed! Possible reasons: 1) Bad SQL 2) Table doesn't exist, $query->builder->sql: \n %s", $query->builder->sql));
			}
			if($query->_query_type == 'insert') {
                $query->_last_insert_id = $database->getConnection()->lastInsertId();
            }
			if(preg_match('/^(SELECT|SHOW)/', $query->builder->sql)) {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
		} catch (PDOException $e) {
			throw new Exception($e->getMessage());
		}
		return $result;
	}

    public function warpspeed($query, $instance) {
        $table = $query->model;
        $start = microtime(true);
        $pk = SalamaBuild::getPk($query->model);
        if($query->_query_type != SalamaQuery::$DELETE) {
            if($query->getItem($pk)) {
                $query->_query_type = SalamaQuery::$UPDATE;
            }
        }
        $query->builder->prepare($query, $instance);
        $items = $query->database->execute($query, $instance);
        $query->clearDirty();
        $query->executed = true;

		if($query->_query_type==SalamaQuery::$INSERT && $query->model ) {
            $pk_name = SalamaBuild::getPk($query->model);
            $query->setItem($pk_name, $query->_last_insert_id, false);
		}

		if(count($query->_involved_tables) == 1 ) { # single table query
            // SELECT
            if($items) {
                $res = $query->hydrateResult($items, $query->builder->getAlias($table, $query), false);
                $items = $res[$table];
            }
            $count = count($items);
            for($i=0; $i<$count; $i++) {
                $model = $query->model;
                $instance->_row[$i] = clone $instance;
                $instance->_row[$i]->_row = array();
                $instance->_row[$i]->_query = array();
                if($i==0) {
                    $instance->_row[$i]->_query = $query;
                } else {
                    $instance->_row[$i]->_query = clone $query;
                }
                $instance->_row[$i]->_query->setItems($items[$i]);
            }

		} elseif($query->_query_type==SalamaQuery::$INSERT || (isset($data['raw']) && !$items)  ) {
            $query->setItems($items);
        } elseif(isset($data['raw'])) {
            $last_query = $query->builder->sql;
            foreach(SalamaData::$c as $model_=>$data_) {
                $table = $query->builder->getRealTableName($model_);
                if(strpos($last_query, $table)) {
                    $query->_involved_tables[] = $table;
                }
            }
            $query->setItems($items);
		} else {
            // JOIN: setup instances for every table with their respective data
			foreach($query->_involved_tables as $field=>$tbl) {
				$res = $query->hydrateResult($items, $tbl['alias'], true);
				if($tbl['table'] == $query->getTable()) {
                    $pk = SalamaBuild::getPk($tbl['table']);
					$unique = array_intersect_key($res, array_unique($res));
				}
                $query->setItems($res, true);
			}
		}

		if($query->_query_type == SalamaQuery::$INSERT) {
            $query->setItem($pk, $query->_last_insert_id, false);
        }

        $job_class = $query->jobs;
        if($query->_query_type == SalamaQuery::$INSERT) {
            $job = $job_class->getJob(SalamaQuery::$INSERT);
            # update all query params to be available in resultset items
            foreach($job->params[0] as $k=>$v) {
                $query->setItem($k, $v, false);
            }
        }
        if($query->_query_type == SalamaQuery::$UPDATE) {
            if($job = $job_class->getJob('set')) {
                # update all query params to be available in resultset items
                foreach($job->objs as $k=>$q) {
                    $query->setItem($q->name, $q->arguments[0], false);
                }
            }
        }

        # housekeeping...
        $query->jobs = null;
        $query->jobs();

        return $query;
    }
}

?>