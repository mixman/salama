<?php

require_once(dirname(__FILE__) . '/../queries/QueryTest.php');

class QueryTests {
    public static function suite() {
        $suite = new PHPUnit_Framework_TestSuite('queries');
        $suite->addTestSuite('QueryTest');
        return $suite;
    }

}

?>