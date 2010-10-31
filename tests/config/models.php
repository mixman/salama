<?php

class Model extends SalamaModel {
	var $_meta_model = '
		tableCaseFormat	 = UpperCamelCase,
		tableAliasFormat = InitialLowerCase
	';
	#@TODO engine=innodb,charset=utf8,collation=utf8
}

class User extends Model {
	# fields
	var $id = 'type=IntegerField,primaryKey=true,autoIncrement=true';
	var $username = 'type=CharField,maxLength=50';
	var $sex = 'type=CharField,maxLength=20';
	var $login = 'type=IntegerField,maxLength=11';
	var $updated = 'type=IntegerField,maxLength=11';	
	# relations
	var $comments = 'hasMany=UserComment';
	var $info = 'hasOne=UserInfo';
	var $tags = 'hasMany=Tag,through=TagUser';

	# signals
	public function preInsert() {
		$this->updated = 313;
	}	
	
	# custom methods
	public function findByName($name) {
		return User::where(q::username($name));
	}	
	
	public function getRowId() {
		return $this->id;
	}

    public function getNull() {
        return null;
    }
}

class UserInfo extends Model {
	# fields
	var $total = 'type=IntegerField';
	var $gallery = 'type=IntegerField';
	var $journal = 'type=IntegerField';	
	# relations
	var $user_id = 'belongsTo=User,onDelete=cascade';
}

class UserComment extends Model {
	# fields
	var $comment = 'type=TextField';
	var $pub_date = 'type=IntegerField';
	# relations
	var $user_id = 'belongsTo=User,onDelete=cascade';	
	# meta
	var $_meta = 'tableName=user_comment';	
}

class Tag extends Model {
	# fields
	var $name = 'type=CharField,maxLength=50';	
	# relations
	var $users = 'hasMany=User,through=TagUser';
}

class TagUser extends Model {
	# fields
	var $golden_path = 'type=CharField,maxLength=42';		
	# relations
	var $user_id = 'belongsTo=User,onDelete=cascade';
	var $tag_id = 'belongsTo=Tag'; 
}

?>
