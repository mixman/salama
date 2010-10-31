<?php

class SalamaMysqlBuilder extends SalamaSqlBuilder {
    public function getBuilder() {
        return new SalamaMysqlBuilder();
    }

}

?>