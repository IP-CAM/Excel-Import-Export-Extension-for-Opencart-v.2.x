<?php

namespace KiboImex\Fields;

use ControllerCatalogKiboimex;
use KiboImex\ExportRow;
use KiboImex\Field;

class Store implements Field {

    private $ctl;

    public function __construct(ControllerCatalogKiboimex $controller) {
        $this->ctl = $controller;
    }

    public function import(int $productId, ExportRow $row) {
        $this->ctl->db->query("
            INSERT IGNORE INTO `" . DB_PREFIX . "product_to_store`
            SET
                product_id = $productId,
                store_id = 0
        ");
    }

    public function export(array $product): ExportRow {
        return new ExportRow();
    }

}