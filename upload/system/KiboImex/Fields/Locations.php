<?php

namespace KiboImex\Fields;

use ControllerCatalogKiboimex;
use KiboImex\BaseFields\Base;
use KiboImex\ExportRow;
use KiboImex\Helpers;

class Locations extends Base {

    const FIELD_LOCATIONS = 'Vestigingen';

    public function __construct(ControllerCatalogKiboimex $controller) {
        parent::__construct($controller);
        Helpers::requireColumn($this->ctl->db, 'product', 'locations');
    }

    public function export(array $product): ExportRow {
        $titles = [];

        foreach (explode(',', $product['locations']) as $locationId) {
            if (!$locationId) {
                continue;
            }
            $row = $this->ctl->db->query("
                SELECT
                    ld.title
                FROM `" . DB_PREFIX . "location_description` ld
                JOIN `" . DB_PREFIX . "location` l USING (location_id)
                WHERE
                    ld.location_id = " . (int) $locationId . "
                LIMIT 1
            ")->row;
            if ($row) {
                $titles[] = $row['title'];
            }
        }

        return ExportRow::withField(1504, self::FIELD_LOCATIONS, implode('; ', $titles));
    }

    public function import(int $productId, ExportRow $row) {
        if (!isset($row[self::FIELD_LOCATIONS])) {
            return;
        }

        $titles = Helpers::splitValues($row[self::FIELD_LOCATIONS]);
        $ids = [];

        foreach ($titles as $title) {
            $row = $this->ctl->db->query("
                SELECT
                    ld.location_id
                FROM `" . DB_PREFIX . "location_description` ld
                JOIN `" . DB_PREFIX . "location` l USING (location_id)
                WHERE
                    ld.title = '" . $this->ctl->db->escape($title) . "'
                ORDER BY
                    ld.location_id
                LIMIT 1
            ")->row;
            if (!$row) {
                $this->ctl->addWarning("Ongeldige vestiging: $title");
                continue;
            }
            $ids[] = $row['location_id'];
        }

        $ids = array_unique($ids);

        Helpers::update(
            $this->ctl->db, 'product',
            ['product_id' => $productId],
            ['locations' => implode(',', $ids)]
        );
    }

}