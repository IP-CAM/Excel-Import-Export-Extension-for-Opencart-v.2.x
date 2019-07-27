<?php

namespace KiboImex\Fields;

use ControllerCatalogKiboimex;
use KiboImex\ExportRow;
use KiboImex\Field;
use KiboImex\Helpers;
use KiboImex\UnavailableFieldException;
use Unic;

class Calculator implements Field {

    const ORDER = 2000;
    const FIELD_CALCULATOR = 'Calculator';

    private $ctl;
    private $variables = [];

    public function __construct(ControllerCatalogKiboimex $controller) {
        $this->ctl = $controller;

        Helpers::requireTable($this->ctl->db, 'unic_calc');

        if (!class_exists(Unic::class)) {
            throw new UnavailableFieldException("Class not defined: " . Unic::class);
        }

        $result = $this->ctl->db->query("
            SELECT
                uv.id,
                uv.type,
                uc.label calc_label,
                uv.label var_label
            FROM `" . DB_PREFIX . "unic_var` uv
            JOIN `" . DB_PREFIX . "unic_calc` uc ON uc.id = uv.calc_id
        ");

        foreach ($result->rows as $row) {
            $this->variables[] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'label' => $row['calc_label'] . ': ' . $row['var_label']
            ];
        }
    }

    public function import(int $productId, ExportRow $row) {
        if (!isset($row[self::FIELD_CALCULATOR])) {
            return;
        }

        if ($row[self::FIELD_CALCULATOR] == '') {
            $calculatorId = null;
        } elseif (!($calculatorId = $this->findByTitle('unic_calc', 'id', 'label', $row[self::FIELD_CALCULATOR]))) {
            $this->ctl->addWarning("Ongeldige invoer in veld: " . self::FIELD_CALCULATOR);
        }

        Helpers::update($this->ctl->db, 'product', ['product_id' => $productId], [
            'unic_calc_id' => $calculatorId,
        ]);

        $this->ctl->db->query("
            DELETE FROM `" . DB_PREFIX . "unic_product_var`
            WHERE
                product_id = " . (int) $productId . "
        ");

        foreach ($this->variables as $variable) {
            if (empty($row[$variable['label']])) {
                continue;
            }

            Helpers::insert($this->ctl->db, 'unic_product_var', [
                'product_id' => $productId,
                'var_id' => $variable['id'],
                'value' => Unic::clean($row[$variable['label']], $variable['type']),
            ]);
        }
    }

    private function findByTitle($table, $col_id, $col_title, $title) {
        $result = $this->ctl->db->query("
            SELECT `{$col_id}` AS id
            FROM `" . DB_PREFIX . "{$table}`
            WHERE `{$col_title}` = '" . $this->ctl->db->escape($title) . "'
            ORDER BY `{$col_id}`
        ");
        return $result->row ? $result->row['id'] : '';
    }

    public function export(array $product): ExportRow {
        $row = new ExportRow();

        $result = $this->ctl->db->query("
            SELECT uc.label
            FROM `" . DB_PREFIX . "unic_calc` uc
            WHERE uc.id = " . (int) $product['unic_calc_id'] . "
        ");

        $row = $row->addField(self::ORDER, self::FIELD_CALCULATOR, $result->row ? $result->row['label'] : '');

        $result = $this->ctl->db->query("
            SELECT
                uc.label calc_label,
                uv.label label,
                uv.type,
                upv.value
            FROM `" . DB_PREFIX . "unic_var` uv
            JOIN `" . DB_PREFIX . "unic_calc` uc ON uv.calc_id = uc.id
            LEFT JOIN `" . DB_PREFIX . "unic_product_var` upv ON
                upv.var_id = uv.id
                AND upv.product_id = " . (int) $product['product_id'] . "
                AND uc.id = " . (int) $product['unic_calc_id'] . "
            ORDER BY uc.label, uv.sort
        ");

        foreach ($result->rows as $calc_var) {
            if ($calc_var['type'] == 'boolean') {
                $value = $calc_var['value'] ? 'ja' : '';
            } else {
                $value = $calc_var['value'];
            }
            $row = $row->addField(self::ORDER, $calc_var['calc_label'] . ': ' . $calc_var['label'], $value);
        }

        return $row;
    }

}