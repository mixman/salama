<?php

class CoreTest extends SalamaTestCase {
    protected function setUp() {
        $settings = array('config_dir'=>dirname(__FILE__).'/../config');
        Salama::bootstrap($settings);
    }

    protected function tearDown() {
    }

    public function testIdentity() {
        $users = User::all();
        $this->assertTrue($users instanceof User);
        $items = array('User'=>array(0=>array('username'=>3), 1=>array('username'=>2), 2=>array('username'=>'1')));
        $users->getQuery()->items = $items;
        foreach($users as $user) {
            $this->assertTrue($users instanceof User);
            $this->assertTrue($user instanceof User);
            foreach($user->info as $info) {
                $this->assertTrue($info instanceof UserInfo);
            }
        }
        $this->assertTrue($users instanceof User);
    }

    public function testModelMethod() {
        $this->assertEquals(User::getNull(), null);
    }

}
?>