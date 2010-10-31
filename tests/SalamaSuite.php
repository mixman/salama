<?php

class SalamaSuite extends PHPUnit_Framework_TestSuite {
	public static $users;
	
	public static function suite() {
		return new SalamaSuite('SalamaSuite');
	}	 
    	
}

?>