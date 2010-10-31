<?php

class QueryTest extends SalamaTestCase {
    protected function setUp() {
        $settings = array('config_dir' => dirname(__FILE__) . '/../config');
        Salama::bootstrap($settings);
        # 1. create models, database
        $runner = new SalamaRunner();
        $runner->build();
        User::raw("CREATE DATABASE IF NOT EXISTS `salamatest`")->goraw();
        $runner->syncdb();

        # 2. add test data
        SalamaSuite::$users = array(
            1 => 'good_bit',
            2 => 'evil_bit'
        );
        foreach (SalamaSuite::$users as $k => $username) {
            # transaction support
            #User::begin();

            $user = User::create();
            $user->username = $username;
            $user->login = $k;
            $user->save();

            $u = UserInfo::create();
            $u->total = $k;
            $u->user_id = $user->id; # @TODO when relational, this will be automatically set
            $u->save();

            for ($i = 0; $i < 2; $i++) {
                $u = UserComment::create();
                $u->user_id = $user->id; # @TODO when relational, this will be automatically set
                $u->comment = "Hello from {$username}!";
                $u->pub_date = $i;
                $u->save();
            }

            $tag = Tag::create();
            $tag->name = "Blue";
            $tag->save();

            $tu = TagUser::create();
            $tu->user_id = $user->id;
            $tu->tag_id = $tag->id;       // @TODO $tu->tag = $t; should do the same thing
            $tu->golden_path = $username . " bets on " . rand();
            $tu->save();

            $tag2 = Tag::create();
            $tag2->name = "Yellow";
            $tag2->save();

            $tu = TagUser::create();
            $tu->user_id = $user->id;
            $tu->tag_id = $tag2->id;
            $tu->golden_path = $username . " bets on " . rand();
            $tu->save();

            #User::commit();
        }
    }

    protected function tearDown() {
        # @TODO can't drop database, as syncdb isn't inserting test data atm. :<
        #User::raw("DROP DATABASE `salamatest`")->call();
        User::raw("TRUNCATE User")->goraw();
        User::raw("TRUNCATE Tag")->goraw();
        User::raw("TRUNCATE TagUser")->goraw();
        User::raw("TRUNCATE UserInfo")->goraw();
        User::raw("TRUNCATE UserComment")->goraw();
        User::raw("TRUNCATE user_comment")->goraw();
    }

    public function testSelectOne() {
        $res = User::where(q::id(1));
        $this->assertEquals(1, $res->id);
        $this->assertEquals(SalamaSuite::$users[1], $res->username);
    }

    public function testSelectOneLazy() {
        $res = User::where(q::id(1));
        $this->assertEquals(1, $res->id);
        $this->assertEquals(SalamaSuite::$users[1], $res->username);
    }

    public function testCustomMethod() {
        $res = User::where(q::id(2));
        $this->assertEquals($res->id, $res->getRowId());
    }

    public function testLoopValues() {
        $users = User::all();
        foreach ($users as $k => $user) {
            $this->assertEquals($user->username, SalamaSuite::$users[$k + 1]);
        }
        foreach ($users as $k => $user) {
            $this->assertEquals($user->username, SalamaSuite::$users[$k + 1]);
        }
        $users = User::all();
        foreach ($users as $k => $user) {
            $this->assertEquals($user->username, SalamaSuite::$users[$k + 1]);
        }
        foreach ($users as $k => $user) {
            $this->assertEquals($user->username, SalamaSuite::$users[$k + 1]);
        }
    }

    public function testCustomMethodInLoop() {
        $users = User::all();
        foreach ($users as $user) {
            $this->assertEquals($user->id, $user->getRowId());
        }
    }

    public function testUpdate() {
        $sex = "unknown";
        User::set(q::sex($sex))->where(q::id(1))->update();
        $res = User::where(q::id(1));
        $this->assertEquals($res->sex, $sex);
    }

    public function testUpdateMulti() {
        $sex = "unknown";
        $username = 'Wagner';
        User::set(q::sex($sex))->set(q::username($username))->where(q::id(1))->update();
        $res = User::where(q::id(1));
        $this->assertEquals($res->sex, $sex);
        $this->assertEquals($res->username, $username);
    }

    public function testUpdateMultiOverride() {
        $sex = "unknown";
        $username = 'Wagner';
        $username_override = 'Viivi';
        User::set(q::sex($sex))
                ->set(q::username($username))
                ->where(q::id(1))
                ->set(q::username($username_override))
                ->update();
        $res = User::where(q::id(1));
        $this->assertEquals($res->sex, $sex);
        $this->assertEquals($res->username, $username_override);

        // flip
        $sex = "unknown";
        $username = 'Viivi';
        $username_override = 'Wagner';
        User::set(q::sex($sex))
                ->set(q::username($username))
                ->where(q::id(1))
                ->set(q::username($username_override))
                ->update();
        $res = User::where(q::id(1));
        $this->assertEquals($res->sex, $sex);
        $this->assertEquals($res->username, $username_override);
    }

    public function testUpdateWithData() {
        $username = 'johndagger';
        $res = User::where(q::id(1))->update(array('username' => $username));
        $this->assertEquals($res, true);
        $res = User::where(q::id(1));
        $this->assertEquals($res->username, $username);
    }

    public function testSelectUpdate() {
        $sex = "good";
        $res = User::where(q::id(1));
        $res->sex = $sex;
        $res->save();
        $res = User::where(q::id(1));
        $this->assertEquals($res->sex, $sex);
    }

    public function testSelectUpdateNonUpdatedValueAvailable() {
        $username = 'bobby';
        $res = User::where(q::id(1));
        $sex = $res->sex;
        $code = $res->set(q::username($username))->update();
        $this->assertEquals($res->sex, $sex);
        $this->assertEquals($res->username, $username);
    }

    public function testSelectLoopInvalidRelationException() {
        $this->setExpectedException('Exception');
        $res = User::where(q::id(1));
        foreach ($res as $k => $v) {
            $v->comment;
        }
    }

    public function testSelectLoop() {
        $res = User::where(q::id(1));
        foreach ($res as $k => $v) {
            $this->assertEquals(SalamaSuite::$users[1], $v->username);
        }
    }

    public function testSelectOutOfRange() {
        $res = User::where(q::id(99999999));
        $this->assertEquals(null, $res->username);
    }

    public function testInsertUpdateDelete() {
        $sex = "Male";
        $res = User::create();
        $res->username = SalamaSuite::$users[1];
        $res->sex = $sex;
        $res->login = 1000;
        $res->save(); # insert

        $q = User::where(q::login(1000));
        $this->assertEquals(SalamaSuite::$users[1], $q->username);
        $this->assertEquals($sex, $q->sex);
        $this->assertEquals(1000, $q->login);

        # sex for male was updated?
        $q = User::where(q::id($q->id));
        $this->assertEquals($sex, $q->sex);

        $sex_female = "Female";
        $res->sex = $sex_female;
        $res->save(); # update
        # sex for female was updated?
        $q = User::where(q::id($res->id));
        $this->assertEquals($sex_female, $res->sex);

        # delete
        $pk = $q->id;
        $res->delete();
        $q = User::where(q::id($pk));
        $this->assertEquals(false, $q->id);
    }

    # 1:1 TODO

    public function testHasManyInLoop() {
        $users = User::where(q::id(1));
        foreach ($users as $user) {
            foreach ($user->comments as $comment) {
                $this->assertEquals($user->id, $comment->user_id);
            }
        }
    }

    public function testHasManyCorrectValues() {
        $users = User::where(q::id(1));
        foreach ($users as $user) {
            $comments = $users->comments;
            if ($comments[0]->id != $comments[1]->id) {
                $this->assertEquals(1, 1);
            }
            if ($users->comments[0]->id != $users->comments[1]->id) {
                $this->assertEquals(1, 1);
            }
        }
    }

    public function testHasManyDirectAccess() {
        $res = User::where(q::id(1));
        foreach ($res as $val) {
            if (is_array($val->comments->getSet())) {
                $this->assertEquals(1, 1);
            }
            $this->assertEquals(count($val->comments->getSet()), 2);
            $this->assertEquals(SalamaSuite::$users[1], $val->username);
        }
    }

    public function testOffset() {
        $users = User::all();
        $this->assertEquals($users[1]->username, SalamaSuite::$users[2]);
        $this->assertEquals($users[0]->username, SalamaSuite::$users[1]);
    }

    public function testIdentityAware() {
        $user = User::all();
        if ($user instanceof User)
            $this->assertTrue(true);
        if ($user->info instanceof UserInfo)
            $this->assertTrue(true);
        $info = $user->info;
        if ($info instanceof UserInfo)
            $this->assertTrue(true);
        if ($user instanceof User)
            $this->assertTrue(true);
        $user = User::all();
        if ($info instanceof UserInfo)
            $this->assertTrue(true);
        if ($user instanceof User)
            $this->assertTrue(true);
        if ($user instanceof User)
            $this->assertTrue(true);
    }

    public function testIdentityAwareTwo() {
        $res = User::all();
        foreach ($res as $k => $v) {
            if ($res instanceof User)
                $this->assertTrue(true);
            $this->assertType('User', $v);
            foreach ($v->comments as $k2 => $v2) {
                if ($res instanceof User)
                    $this->assertTrue(true);
                if ($v2 instanceof UserComment)
                    $this->assertTrue(true);
            }
        }
    }

    public function testIdentityAwarePHP() {
        $users = User::where(q::id(1));
        foreach ($users as $user) {
            $this->assertEquals(get_class($user), 'User');
            foreach ($users->comments as $comment) {
                $this->assertEquals(get_class($user), 'User');
                $this->assertEquals(get_class($comment), 'UserComment');
                $this->assertEquals(get_class($user), 'User');
            }
        }
    }

    public function testCommentCount() {
        $comments = UserComment::where(q::user_id(1));
        $this->assertEquals(2, count($comments));
    }

    public function testModelOffsetAccess() {
        $users = User::create();
        $one = "thinking about it";
        $two = "on better days";
        $this->setExpectedException('Exception');
        $users[0]->sex = $one;
    }

    public function testRelationIterationResult() {
        $users = User::where(q::id(1));
        $i = 1;
        foreach ($users as $user) {
            $j = 0;
            foreach ($users->comments as $comment) {
                $this->assertEquals($j, $comment->pub_date);
                $j++;
            }
            $this->assertEquals($i, $user->login);
            $i++;
        }
    }

    public function testHasOneInLoop() {
        $users = User::where(q::id(1));
        foreach ($users as $user) {
            foreach ($users->info as $i) {
                $this->assertEquals(1, $i->total);
                $this->assertEquals(SalamaSuite::$users[1], $user->username);
            }
        }
    }

    public function testDirectAccessAndHasOne() {
        $users = User::where(q::id(2));
        foreach ($users as $user) {
            $this->assertEquals(2, $user->info->total);
            $this->assertEquals(SalamaSuite::$users[2], $user->username);
        }
    }

    public function testQueryLimit() {
        $comments = UserComment::where(q::user_id(1))->limit(2);
        $this->assertEquals(2, count($comments));
        $comments = UserComment::where(q::user_id(1))->limit(1);
        $this->assertEquals(1, count($comments));
    }

    public function testPreInsertHook() {
        $sex = "Unknown";
        $res = User::create();
        $res->username = SalamaSuite::$users[1];
        $res->sex = $sex;
        $res->save();
        $this->assertEquals($res->updated, 313);
        $this->assertEquals($res->sex, $sex);
        $this->assertEquals($res->username, SalamaSuite::$users[1]);
    }

    public function testCreateUser() {
        $name = "BobbyTables";
        $u = new User();
        $u->username = $name;
        $this->assertEquals($u->id, null);
        $u->save();
        $this->assertEquals($u->username, $name);
        $this->assertNotEquals($u->id, null);
        $new_user = User::where(q::id($u->id));
        $this->assertEquals($new_user->username, $name);
    }

    public function updateOffset() {
        $name = "JaneTables";
        $users = User::all();
        $pk = $users[1]->id;
        $users[1]->username = $name;
        $users[1]->save();
        $this->assertEquals($user[1]->id, $pk);

        $user = User::where(q::id($users[1]->id));
        $this->assertEquals($user->username, $name);
    }

    public function relation() {
        $u = User::where(q::id(1));
        $this->assertEquals(SalamaSuite::$users[0], $u->username);
        $this->assertEquals($u->info->total, 1);
        $this->assertEquals($u->username, $name);
        $this->assertEquals($u->info->user_id, $u->user_id);
    }

    public function relationMany() {
        $u = User::where(q::id(1));
        $this->assertEquals(SalamaSuite::$users[0], $u->username);
        $this->assertEquals($u->comments[0]->pub_date, 0);
        $this->assertEquals($u->comments[0]->user_id, $u->id);
        $this->assertEquals($u->username, $name);
        $this->assertEquals($u->comments[1]->pub_date, 1);
        $this->assertEquals($u->comments[1]->user_id, $u->id);
        foreach ($u->comments as $k => $v) {
            $this->assertEquals($v->pub_date, $k);
            $this->assertEquals($v->user_id, $u->id);
        }
        $this->assertEquals(count($u->comments), 2);
    }

}

?>