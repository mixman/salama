<?php

date_default_timezone_set('Europe/Helsinki');
# when no errors in SalamaBuild et...
#error_reporting(E_ALL | E_STRICT);
# @TODO tests will only run with this error_reporting level due E_NOTICEs!
error_reporting(E_ALL & ~E_NOTICE);
set_time_limit(0);

# phpUnit
if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'AllTests::main');
}
require_once 'PHPUnit/Framework.php';
require_once 'PHPUnit/TextUI/TestRunner.php';

# custom phpUnit classes
require_once('SalamaTestCase.php');
require_once('SalamaSuite.php'); # suite-level setUp&tearDown
# Salama
require_once(dirname(__FILE__) . '/../salama/Salama.php');

class AllTests {

    public static function main() {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }

    public static function suite() {
        $suite = new SalamaSuite('Salama ORM');
        $testDir = dirname(__FILE__) . '/AllTests';
        require_once($testDir . '/QueryTests.php');
        $suite->addTest(QueryTests::suite());

        require_once($testDir . '/CoreTests.php');
        $suite->addTest(CoreTests::suite());

        return $suite;
    }

}

if (PHPUnit_MAIN_METHOD == 'AllTests::main') {
    AllTests::main();
}
?>