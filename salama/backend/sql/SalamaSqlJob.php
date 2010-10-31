<?php

class SalamaSqlJob extends SalamaJob {
    public function _sel_fields($fields, $alias, $quote) {
        foreach($fields as $j=>$field) {
            $select_fields[] = "{$alias}.{$quote}{$field}{$quote} AS {$alias}__{$field}";
        }
        return $select_fields;
    }
	public function select($args, $query) {
        $backend = SalamaDatabaseManager::getCurrentBackend();
        $quote = $backend['quote'];
        if(empty($quote)) $quote = '';
		# setup select(), if no select() used and not a raw() query
		if(!$this->builder->recursive_array_search('raw', $query->methods)) {
			foreach($query->_involved_tables as $k=>$array) {
				$alias = $this->builder->getAlias($array['table'], $query);
                $select_fields = $this->_sel_fields(SalamaData::$c[$array['table']][SalamaBuild::$meta]['fields'], $alias, $quote);
			}            
			$sql = implode(', ', $select_fields);
		} else {
            $select_key = $this->builder->recursive_array_search('select', $query->methods);
            $select_sql = isset($query->methods[$select_key][1][0]) ? $query->methods[$select_key][1][0] : null;
			$sql = $this->select($select_sql);
		}
        $this->sql = $sql;
	}

	public function update($args, $query) {
        $this->params = $args;
	}

	public function insert($args, $query) {
        $this->params = $args;
	}

    public function delete($args, $query) {
        $this->params = $args;
	}

	public function set($args, $query, $override=true) {
        $query_object = $args[0];
        // if set() exists, skip for any same Model.field value
        $existing = false;
        foreach($this->objs as $k=>$q) {
            if($q instanceof q) {
                if($q->name == $query_object->name) {
                    if($override) {
                        $existing = $k;
                        break;
                    } else {
                        return;
                    }
                }
            }
        }

        if($existing !== false) {
            $this->params[$existing] = $query_object->arguments;
            $this->objs[$existing] = $query_object;
        } else {
            $this->params[] = $query_object->arguments;
            $this->objs[] = $query_object;
        }
        $this->sql = substr(str_repeat('?, ', count($this->params)), 0, -2);
	}

	public function from($args, $query) {
		$this->sql = $this->builder->getRealTableName($query->model)." AS ".SalamaBuild::getAlias($query->model);
	}

	public function where($args, $query, $override=true) {
        if(empty($args)) {
            throw new Exception(__METHOD__." missing arguments");
        }

		$obj = $args[0];

        $this->params[] = $obj->arguments;
        $this->objs[] = $obj;

        $query_sql = sprintf('%s%s?',
                        $obj->name,
                        SalamaMysqlBuilder::$sql_map[$obj->method]
                        );
        $this->builder->getColumns($query_sql);
        $this->sql = $query_sql;
	}
    /*
    public function whereSql($job, $query) {
        $sql = array();
        foreach($job->objs as $obj) {
            $query_sql = sprintf('%s%s?',
                            $obj->name,
                            SalamaMysqlBuilder::$sql_map[$obj->method]
                            );
            $this->builder->getColumns($query_sql);
            $sql[] = $query_sql;
        }
    }*/

	public function limit($args, $query) {
        // @TODO PDO::PARAM_INT should be abstracted to connection->getInt()
		$this->pdo_param[] = PDO::PARAM_INT;
		$this->params[] = $args[0];
		$this->sql = '?';
	}

	public function groupby($args, $query) {
		$this->pdo_param[] = PDO::PARAM_STR;
		$this->params[] = $args[0];
		$this->sql = '?';
	}

	public function order($args, $query) {
		$this->pdo_param[] = PDO::PARAM_STR;
		if(is_array($args[1])) {
			foreach($args[1] as $t=>$v) {
				$this->params[] = $args[1][$t];
			}
		}
		$this->sql = $args[0];
	}

	public function raw($args, $query) {
		if(!empty($args[1])) {
			foreach($args[1] as $t=>$v) {
				$this->params[][] = $args[1][$t];
			}
		}
		$this->sql = $args[0];
	}

	public function join($args, $query) {
		$joins = $this->setupJoin($args, $query);
		$this->sql = implode("\n", $joins);
	}
}

?>