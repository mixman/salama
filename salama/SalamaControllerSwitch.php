<?php

class SalamaControllerSwitch {

    public function setItem($key=null, $value=null, $set_dirty=true) {
        $this->items[$key] = $value;
        if ($set_dirty) {
            $this->setDirty($key);
        }
    }

    public function getItem($key=null) {
        return isset($this->items[$key]) ? $this->items[$key] : null;
    }

    public function getItems() {
        return $this->items;
    }

    public function setItems($data, $update=false) {
        if (isset($this->items) && $update) {
            $this->items += (array) $data;
        } else {
            $this->items = $data;
        }
        return $this->items;
    }

    public function getTable() {
        return $this->model;
    }

}

?>