<?php
namespace Catalog\GetThis;

class GetThisLoader {
    protected $params;  // controller's $this->params

    function __construct($params) {
        $this->params = $params;
    }

    public function isOtherLib() {
        //TODO
        return true;
    }
}
