<?php

namespace KiboImex\Fields;

use ControllerCatalogKiboimex;
use KiboImex\BaseFields\Base;
use KiboImex\ExportRow;
use KiboImex\Helpers;

class Um extends Base {

    const FIELD_UM = 'Um';

    public function __construct(ControllerCatalogKiboimex $controller) {
        parent::__construct($controller);
        Helpers::requireColumn($this->ctl->db, 'product', 'um');
    }

    public function export(array $product): ExportRow {
        return (new ExportRow())
            ->addField(1040, self::FIELD_UM, Helpers::unescape($product['um']));
    }

    public function import(int $productId, ExportRow $row) {
        if (!isset($row[self::FIELD_UM])) {
            return;
        }

        Helpers::update(
            $this->ctl->db,
            'product',
            ['product_id' => $productId],
            ['um' => $row[self::FIELD_UM]]
        );
    }

}