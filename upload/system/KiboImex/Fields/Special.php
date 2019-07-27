<?php

namespace KiboImex\Fields;

use KiboImex\BaseFields\Base;
use KiboImex\ExportRow;
use KiboImex\Helpers;

class Special extends Base {

    const FIELD_SPECIAL = "Aanbieding";

    public function export(array $product): ExportRow {
        return ExportRow::withField(1600, self::FIELD_SPECIAL, $this->getText($product['product_id']));
    }

    private function getText(int $productId): string {
        $special = $this->ctl->db->query("
            SELECT
                price
            FROM `" . DB_PREFIX . "product_special`
            WHERE
                {$this->getConditions($productId)}
            ORDER BY
                priority, price, product_special_id
            LIMIT 1
        ")->row;

        if (!$special) {
            return '';
        }

        return sprintf('%.2f', $special['price']);
    }

    private function getConditions(int $productId): string {
        return "
            product_id = " . (int) $productId . "
            AND customer_group_id = 1
            AND date_start = '0000-00-00'
            AND date_end = '0000-00-00'
        ";
    }

    public function import(int $productId, ExportRow $row) {
        if (!isset($row[self::FIELD_SPECIAL])) {
            return;
        }

        $text = $row[self::FIELD_SPECIAL];

        if ($text == $this->getText($productId)) {
            return;
        }

        $this->ctl->db->query("
            DELETE FROM `" . DB_PREFIX . "product_special`
            WHERE {$this->getConditions($productId)}
        ");

        if ($text == '') {
            return;
        }

        $value = Helpers::parseNumber($text);

        if ($value === false) {
            $this->ctl->addWarning("Ongeldige invoer in veld: " . self::FIELD_SPECIAL);
        }

        Helpers::insert($this->ctl->db, 'product_special', [
            'product_id' => $productId,
            'customer_group_id' => 1,
            'price' => $value,
        ]);
    }

}