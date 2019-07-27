<?php

namespace KiboImex\BaseFields;

use ControllerCatalogKiboimex;
use KiboImex\Field;

abstract class Base implements Field {

    protected $ctl;

    public function __construct(ControllerCatalogKiboimex $controller) {
        $this->ctl = $controller;
    }

}