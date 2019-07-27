<?php

namespace KiboImex;

use ControllerCatalogKiboimex;

interface Field {

    public function __construct(ControllerCatalogKiboimex $controller);
    public function export(array $product): ExportRow;
    public function import(int $productId, ExportRow $row);

}