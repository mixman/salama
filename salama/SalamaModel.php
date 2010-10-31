<?php

class SalamaModel extends SalamaController {
    public function preSelect() {}
    public function postSelect() {}
    public function preDelete() {}
    public function postDelete() {}
    public function preInsert() {}
    public function postInsert() {}
    public function preUpdate() {}
    public function postUpdate() {}

    public function __construct($via_callstatic=false) {
        if (Salama::$bootstrapped) {
            $this->_prepare($this, get_class($this));
        }
    }

    public static function __callStatic($name, $arguments) {
        if (!Salama::$bootstrapped) {
            Salama::bootstrap();
        }
        $class = get_called_class();
        $model = new $class();
        $model = $model->getModelInstance($class);
        SalamaQuery::add($model, $class, null);
        if (count($arguments) == 1) {
            $arguments = $arguments[0];
        }
        # @TODO wrap around SalamaQuerySet
        return $model->$name($arguments);
    }

    public function getPk() {
        return $this->id;
    }

    public function getSet($items=false) {
        $query = $this->getQuery();
        $this->evaluateLazyCall($query);
        $rows = array();
        foreach ($this->_row as $model) {
            if ($items) {
                $rows[] = $this->getItems();
            } else {
                $rows[] = "" . $model . "";
            }
        }
        return $rows;
    }

    public function getItems() {
        return $this->getQuery()->getItems();
    }

}

?>