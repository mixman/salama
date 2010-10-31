<?php

class SalamaRelation extends SalamaController {
	# 1:1
	public static $belongsTo = 'belongsto';
	public static $hasOne = 'hasone';
	# 1:m
	public static $hasMany = 'hasmany';
	# m:m
	public static $through = 'through';

    // UNFINISHED: relational queries
    public function queryRelation($salama, $query) {
        $model_as_relation = $query->model;
        $relation_alias_or_model_name = $query->model_relation;
        // (mpa, foreign_key, local_key, ongoing 1:m/JOIN)
		$map = $this->getMap($model_as_relation, $query->model, $relation_alias_or_model_name, $salama->_join);
        $res = $this->executeRelationalQuery($salama, $query, $model_as_relation, $map);

        $query->_items[$model_as_relation] = $res->_items[$model_as_relation];
    }

    // UNFINISHED: relational IN query
	public function executeRelationalQuery($salama, $query, $relation, $map) {
        $q = Model::from($relation);
        throw new Exception("NOT SUPPORTED");
		return $q->where($map['foreignkey']." IN(?)", $this->get_fk_values($salama, $query,$map['localkey']))->call();
	}

    // map PK=>FK relation for direct Table joins
    public static function getMap($current_class, $target_table, $relation=null, $join=false) {
		if(isset(SalamaData::$c[$relation])) {
			# TABLE: (possible) belongsTo (foreignKey) relation via Table; FK JOIN
			$target_table = $current_class;
			$map = self::$belongsTo;
            if(SalamaBuild::getRelation($relation, $target_table, 'ref')) {
                list($fk, $pk) = SalamaBuild::getRelation($relation, $target_table, 'both');
			}
			# inverse belongsTo
			if(empty($fk)) {
				$map = self::$hasOne;
                list($pk, $fk) = SalamaBuild::getRelation($target_table, $relation, 'both');
			}
			# check for hasMany under JOIN conditions
			if($join) {
				if(isset(SalamaData::$rel[self::$hasMany][$target_table][$relation])) {
					$relation = SalamaData::$rel[self::$hasMany][$target_table][$relation][0];
					$map = self::$hasMany;
				}
			}
		} else {
			# hasOne/hasMany relation (fieldname)
            if(isset(SalamaData::$c[$target_table][$relation])) {
                $field = SalamaData::$c[$target_table][$relation]; # Table.column_name (key=>val settings)
                if(isset($field[self::$belongsTo])) {
                    $map = self::$belongsTo;
                } elseif(isset($field[self::$hasOne])) {
                    $map = self::$hasOne;
                } elseif(isset($field[self::$hasMany])) {
                    $map = self::$hasMany;
                }

                if(isset($field['foreignkey'])) {
                    $fk = $field['foreignkey'];
                } elseif(isset($field[self::$through])) {
                    # through (m:m)?
                    list($pk, $fk) = SalamaBuild::getRelation($field[self::$through], $target_table, 'both');
                } else {
                    list($pk, $fk) = SalamaBuild::getRelation($current_class, $target_table, 'both');
                }
            } else {
				# FK taken from Table we are obtaining data from; _rel[reference][target]
                list($pk, $fk) = SalamaBuild::getRelation($current_class, $target_table, 'both');
			}
		}
		return array(
				'map' => isset($map) ? $map : null,
				'foreignkey' => isset($fk) ? $fk : null,
				'localkey' => isset($pk) ? $pk : null,
				#'through' => isset($through) ? $through : null,
				);
    }

    public function get_fk_values($salama, $query, $pk) {
        $parent_model = $salama->getQuery($query->query_id_parent);
        $fk_value = array();
        if(isset($parent_model->iterator) && ($this->iterator->key() !== null)) {
            $fk_value[] = $parent_model->getItem($pk);
        } else {
            foreach((array)$query->items as $item) {
                $fk_value[] = $item->$pk;
            }
        }
        return $fk_value;
    }
}

?>