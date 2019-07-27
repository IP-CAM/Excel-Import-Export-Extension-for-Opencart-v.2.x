<?php

namespace KiboImex\Fields;

use ControllerCatalogKiboimex;
use KiboImex\BaseFields\ProductValue;

class FVProductGroup extends ProductValue {

    private $values;

    public function __construct(ControllerCatalogKiboimex $controller) {
        parent::__construct($controller);

        $this->values = [];

        $result = $this->ctl->db->query("
            SELECT id, title
            FROM `" . DB_PREFIX . "producticons`
            ORDER BY title, id
        ");

        foreach ($result->rows as $row) {
            $this->values[$row['id']] = trim($row['title']);
        }
    }

    protected function getOrder(): int {
        return 1502;
    }

    protected function getColumn(): string {
        return 'fv_productgroup';
    }

    protected function getLabel(): string {
        return 'Productgroep';
    }

    protected function valueToText($value): string {
        return $this->values[$value] ?? '';
    }

    protected function textToValue(string $text) {
        if ($text === '') {
            return null;
        }

        foreach ($this->values as $id => $label) {
            if (!strcasecmp($text, $label)) {
                return $id;
            }
        }

        $this->warnInvalidValue();

        return null;
    }

}