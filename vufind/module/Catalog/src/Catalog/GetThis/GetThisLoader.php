<?php
namespace Catalog\GetThis;

class GetThisLoader {
    public $record;  // record driver
    public $items;   // holding items

    function __construct($record, $items) {
        $this->record = $record;
        $this->items = $items;
    }

    public function isOtherLib() {
        //TODO
        return true;
    }
}
