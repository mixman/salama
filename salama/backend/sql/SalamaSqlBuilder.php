<?php

class SalamaSqlBuilder {
    public $args = array();
    public $_usedColumns = array();
    public $pdo_param = array();
    public $sql = null;

	# http://dev.mysql.com/doc/refman/5.0/en/select.html
	public static $valid_methods = null;

    public static $sql_select = array(
		'select'=>array(
            'name'=>'SELECT %s',
            'default'=>'*',
            ),
		'from'=>array(
            'name'=>'FROM %s',
            'default'=>null,
            ),
		'join'=>array(),
		'where'=>array(
            'name'=>'WHERE %s',
            'default'=>1,
            ),
		'groupby'=>array(
            'name'=>'GROUP BY %s',
            'default'=>null,
            ),
		'having'=>array(
            'name'=>'HAVING %s',
            'default'=>null,
        ),
		'order'=>array(
            'name'=>'ORDER BY %s',
            'default'=>null,
        ),
		'limit'=>array(
            'name'=>'LIMIT %s',
            'default'=>null,
        ),
    );

    public static $sql_update = array(
		'set'=>'SET',
		#'update'=>'UPDATE',
		'where'=>array(
            'name'=>'WHERE %s',
            ),
    );

    public static $sql_insert = array(
		'insert'=>'INSERT INTO',
    );

    public static $sql_delete = array(
		#'delete'=>'DELETE FROM',
		'where'=>array(
            'name'=>'WHERE %s',
            ),
    );

    public static $sql_raw = array(
        'raw'=>'',
    );

    public static $sql_map = array(
        'eq'=>'=',
        'gt'=>'>',
        'lt'=>'<'
    );

	# join
	public static $types = array(
		'inner',
		'cross',
		'straight',
		'left',
		'left outer',
		'natural',
		'natural left',
		'natural left outer',
		'right',
		'right outer',
		'natural right',
		'natural right outer'
		);

    public static function isValidMethod($method) {
        if(is_null(self::$valid_methods)) {
            self::$valid_methods = array();
            self::$valid_methods += self::$sql_select;
            self::$valid_methods += self::$sql_update;
            self::$valid_methods += self::$sql_insert;
            self::$valid_methods += self::$sql_delete;
            self::$valid_methods += self::$sql_raw;
        }

        return isset(self::$valid_methods[$method]);
    }

	public function prepare($query) {
        $this->getInvolvedTables($query);

        if($query->_query_type == 'select') {
            if($this->recursive_array_search('select', $query->methods) === false) {
                $query->methods[] = array('select', array());
            }
            if($this->recursive_array_search('from', $query->methods) === false) {
                $query->methods[] = array('from', array());
            }
        }

        $job_class = $query->jobs;

		# setup query, executing respective jobs for each valid method
		foreach($query->methods as $k=>$array) {
            list($method, $args) = $array;
			if($this->isValidMethod($method)) {
                if($job = $job_class->getJob($method)) {
                } else {
                    $job = $job_class->createJob($method, $query);
                }
                $job->$method($args, $query);
			}
		}
        $jobs = get_class_methods(get_class($job_class));

        $target_table = null;
		switch($query->_query_type) {
			case 'select':
				$qs = array();

                foreach(self::$sql_select as $name=>$data) {
                    if($job = $job_class->getJob($name)) {
                        $sql_funk = sprintf('%sSql', $job->name);
                        if(in_array($sql_funk, $jobs)) {
                            $sql = $job->$sql_funk($job, $query);
                        } else {
                            $sql = $job->sql ?: self::$sql_select[$job->name]['default'];
                        }
                        $qs[] = sprintf(self::$sql_select[$job->name]['name'], $sql);
                    }
                }

				$qs = implode(' ', $qs);
				break;
			case 'insert':
                $job = $job_class->createJob('insert', $query);
                $pass_items = array();
                foreach($query->getItems() as $i=>$j) {
                    if($i == SalamaBuild::getPk($query->model)) {
                        continue;
                    }
                    $pass_items[$i] = $j;
                }
                $job->insert(array($pass_items), $query);

                // QUERYSTRING: all items
                $cols = array();
                $items = $query->getItems();
                foreach($items as $k=>$v) {
                    if($k != SalamaBuild::getPk($query->model)) {
                        $cols[$k] = $v;
                    }
                }
                list($fieldString, $in) = array(implode(', ', array_keys($cols)), substr(str_repeat('?, ', count($cols)), 0, -2));

				$qs = sprintf("INSERT INTO %s (%s) VALUES (%s)",
                        $this->getRealTableName($query->model),
                        $fieldString,
                        $in);
				break;
			case 'update':
                /* instead of a single update() we do a series of set()'s */
                if($job_set = $job_class->getJob('set')) {
                } else {
                    $job_set = $job_class->createJob('set', $query);
                }

                $pkName = SalamaBuild::getPk($query->model);

                foreach($query->getAllDirty() as $name) {
                    if($name != $pkName) {
                        $job_set->set(array(q::$name($query->getItem($name))), $query, false);
                    }
                }

                if($job_where = $job_class->getJob('where')) {
                } else {
                    $job_where = $job_class->createJob('where', $query);
                    $job_where->where(array(q::$pkName($query->getItem($pkName))), $query);
                }

                // QUERYSTRING: set()job + modified values
                $cols = array();
                foreach($job_set->objs as $k=>$q) {
                    $cols[$q->name] = $q->arguments[0];
                }
                foreach($query->getAllDirty() as $name) {
                    if($name != $pkName) {
                        $cols[$name] = $query->getItem($name);
                    }
                }
                $fieldString = implode('=?, ', array_keys($cols)) . '=?';

                $target_table = $query->model;

				$tableAlias = $this->getAlias($target_table, $query);
				$qs = sprintf("UPDATE %s SET %s WHERE %s",
                        $this->getRealTableName($target_table),
                        $fieldString,
                        $job_where->sql);
				break;
			case 'delete':
                $pkName = SalamaBuild::getPk($query->model);
                if($job_where = $job_class->getJob('where')) {
                } else {
                    $job_where = $job_class->createJob('where', $query);
                    $job_where->where(array(q::$pkName($query->getItem($pkName))), $query);
                }

				$qs = sprintf("DELETE FROM %s WHERE %s",
                        $this->getRealTableName($query->model),
                        $job_where->sql);
				break;
		}

		# raw sql override
        if($job = $job_class->getJob('raw')) {
            $qs = $job->sql;
        }

		$this->sql = $qs;
	}

    # @TODO three different getRealTableName() methods in project
	public function getRealTableName($table) {
		return SalamaData::$c[$table][SalamaBuild::$meta]['tablename'];
	}

    // find out columns used in a query by comparing every models field against a given string
	public function getColumns($string) {
        if(empty($string)) {
            return false;
        }
        foreach(SalamaData::$c as $model=>$fields) {
            foreach($fields as $field=>$v) {
			 	if(strpos($string, $field) !== false) {
                    if(!in_array($field, $this->_usedColumns)) {
                        $this->_usedColumns[] = $field;
                    }
			 	}
            }
        }
	}

    function recursive_array_search($needle, $haystack) {
        foreach($haystack as $key=>$value) {
            if($needle===$value || (is_array($value) && $this->recursive_array_search($needle, $value) !== false)) {
                return $key;
            }
        }
        return false;
    }

    public function getInvolvedTables($query) {
        $table = array();
        if($query->parent) {
            $table[] = array('table'=>$query->parent, 'alias'=>SalamaData::$c[$query->parent][SalamaBuild::$meta]['alias']);
        }
        $table[] = array('table'=>$query->model, 'alias'=>SalamaData::$c[$query->model][SalamaBuild::$meta]['alias']);
        $query->_involved_tables = $table;
    }

    // get alias for Model (eg. "User" AS "u")
	public function getAlias($table, $query) {
		$alias = null;

		foreach($query->_involved_tables as $tbl=>$tbl_alias) {
			if($tbl_alias['table'] == $table) {
				if(empty($tbl_alias['alias'])) {
					$alias = SalamaData::$c[$table][SalamaBuild::$meta]['alias'];
					$query->_involved_tables[$tbl]['alias'] = $alias;
				} else {
					$alias = $tbl_alias['alias'];
				}
				break;
			}
		}

        if(is_null($alias)) {
            foreach(SalamaData::$c as $k=>$v) {
                if($k == $table) {
                    $alias = SalamaData::$c[$table][SalamaBuild::$meta]['alias'];
                    break;
                }
            }
        }
		return $alias;
	}
}

?>