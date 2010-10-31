<?php

require_once(dirname(__FILE__) . '/../queries/CoreTest.php');

class CoreTests {
	public static function suite() {
		$suite = new PHPUnit_Framework_TestSuite('cores');
		$suite->addTestSuite('CoreTest');
		return $suite;
	}
}

?>