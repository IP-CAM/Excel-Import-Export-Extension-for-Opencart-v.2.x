<?php

namespace KiboImex\Fields;

use ControllerCatalogKiboimex;
use KiboImex\ExportRow;
use KiboImex\Field;
use KiboImex\Helpers;
use RuntimeException;

class Basic implements Field {
    
    const FIELD_PRICE = 'Prijs';
    const FIELD_DIMENSIONS = 'LxBxH (cm)';
    const FIELD_NAME = 'Productnaam';
    const FIELD_DESCRIPTION = 'Omschrijving';
    const FIELD_META_TITLE = 'Meta Tag Titel';
    const FIELD_META_DESCRIPTION = 'Meta Tag Omschrijving';
    const FIELD_META_KEYWORD = 'Meta Tag Zoekwoorden';
    const FIELD_TAGS = 'Product Tags';
    const FIELD_MODEL = 'Model';
    const FIELD_SKU = 'SKU';
    const FIELD_UPC = 'UPC';
    const FIELD_EAN = 'EAN';
    const FIELD_JAN = 'JAN';
    const FIELD_ISBN = 'ISBN';
    const FIELD_MPN = 'MPN';
    const FIELD_LOCATION = 'Locatie';
    const FIELD_TAX_CLASS = 'Belastinggroep';
    const FIELD_STATUS = 'Status';
    const FIELD_SORT_ORDER = 'Sorteervolgorde';
    const FIELD_MANUFACTURER = 'Merk';
    const FIELD_CATEGORIES = 'Categorieën';
    const FIELD_FILTERS = 'Filters';
    const FIELD_QUANTITY = 'Voorraad';
    const FIELD_STOCK_STATUS = 'Voorraadstatus';
    const FIELD_WEIGHT = 'Gewicht (kg)';
    const FIELD_IMAGES = 'Afbeelding';

    private $ctl;
    private $languageId;
    private $defaultTaxClassId;
    private $defaultWeightClassId;
    private $defaultLengthClassId;
    private $defaultStockStatusId;
    private $attributes;
    private $attributePresetsEnabled;
    private $attributePresets = [];

    public function __construct(ControllerCatalogKiboimex $controller) {
        $this->ctl = $controller;
        $this->languageId = $this->getLanguageId();
        $this->defaultTaxClassId = $this->getDefaultTaxClassId();
        $this->defaultWeightClassId = $this->getDefaultClassId('weight', 'gewichtseenheid');
        $this->defaultLengthClassId = $this->getDefaultClassId('length', 'lengte-eenheid');
        $this->defaultStockStatusId = $this->getDefaultStockStatusId();
        $this->attributePresetsEnabled = Helpers::columnExists($this->ctl->db, 'product_attribute', 'preset_id');
    }

    private function getLanguageId(): int {
        $result = $this->ctl->db->query("
            SELECT language_id
            FROM `" . DB_PREFIX . "language`
            WHERE status = 1
            ORDER BY sort_order
            LIMIT 1
        ");
        if(!$result->row) {
            throw new RuntimeException("Er is geen taal ingesteld. Controleer de shopinstellingen.");
        }
        return $result->row['language_id'];
    }

    private function getDefaultTaxClassId(): int {
        $result = $this->ctl->db->query("
            SELECT tax_class_id
            FROM `" . DB_PREFIX . "tax_class`
            ORDER BY tax_class_id
            LIMIT 1
        ");
        return $result->row['tax_class_id'] ?? 0;
    }

    private function getDefaultClassId(string $type, string $label): int {
        $result = $this->ctl->db->query("
            SELECT {$type}_class_id
            FROM `" . DB_PREFIX . "{$type}_class`
            WHERE value = 1.0
            ORDER BY {$type}_class_id
            LIMIT 1
        ");
        if(!$result->row) {
            throw new RuntimeException("Er is geen $label met waarde 1.0 gedefinieerd. Pas de shopinstellingen aan.");
        }
        return (int) $result->row["{$type}_class_id"];
    }

    private function getDefaultStockStatusId(): int {
        $result = $this->ctl->db->query("
            SELECT stock_status_id
            FROM `" . DB_PREFIX . "stock_status`
            WHERE
              language_id = {$this->languageId}
              AND name IN ('In Stock', 'Op voorraad')
            ORDER BY stock_status_id
            LIMIT 1
        ");
        return $result->row['stock_status_id'] ?? 0;
    }

    public function import(int $productId, ExportRow $row) {
        $product = [
            'weight_class_id' => $this->defaultWeightClassId,
            'length_class_id' => $this->defaultLengthClassId,
        ];

        $description = [
            'product_id' => $productId,
            'language_id' => $this->languageId,
        ];

        foreach([
            self::FIELD_MODEL => 'model',
            self::FIELD_SKU => 'sku',
            self::FIELD_UPC => 'upc',
            self::FIELD_EAN => 'ean',
            self::FIELD_JAN => 'jan',
            self::FIELD_ISBN => 'isbn',
            self::FIELD_MPN => 'mpn',
            self::FIELD_LOCATION => 'location',
        ] as $field => $column) {
            if (isset($row[$field])) {
                $product[$column] = $row[$field];
            }
        }

        foreach([
            self::FIELD_NAME => 'name',
            self::FIELD_DESCRIPTION => 'description',
            self::FIELD_META_TITLE => 'meta_title',
            self::FIELD_META_DESCRIPTION => 'meta_description',
            self::FIELD_META_KEYWORD => 'meta_keyword',
            self::FIELD_TAGS => 'tag',
        ] as $field => $column) {
            if (isset($row[$field])) {
                $description[$column] = $row[$field];
            }
        }

        if (strlen($description['meta_title']) == 0) {
            $description['meta_title'] = $description['name'];
        }

        if (strlen($product['model']) == 0) {
            $product['model'] = $description['name'];
        }

        if (isset($row[self::FIELD_PRICE])) {
            if (Helpers::parseNumber($row[self::FIELD_PRICE]) === false) {
                $this->ctl->addWarning("Ongeldige invoer in veld: " . self::FIELD_PRICE);
                $product['price'] = 0;
            } else {
                $product['price'] = Helpers::parseNumber($row[self::FIELD_PRICE]);
            }
        }
        
        if (isset($row[self::FIELD_DIMENSIONS])) {
            if(empty($row[self::FIELD_DIMENSIONS])) {
                $product['length'] = 0.0;
                $product['width'] = 0.0;
                $product['height'] = 0.0;
            } elseif(preg_match("~^(" . Helpers::RE_NUMBER . ")x(" . Helpers::RE_NUMBER . ")x(" . Helpers::RE_NUMBER . ")$~i", $row[self::FIELD_DIMENSIONS], $match)) {
                $product['length'] = Helpers::parseNumber($match[1]);
                $product['width'] = Helpers::parseNumber($match[2]);
                $product['height'] = Helpers::parseNumber($match[3]);
            } else {
                $this->ctl->addWarning("Ongeldige invoer in veld: " . self::FIELD_DIMENSIONS);
                $product['length'] = 0.0;
                $product['width'] = 0.0;
                $product['height'] = 0.0;
            }
        }
        
        if (isset($row[self::FIELD_IMAGES])) {
            $images = array();
            foreach(Helpers::splitValues($row[self::FIELD_IMAGES]) as $img) {
                $img_src = $this->resolveImage($img);
                if(!$img_src) {
                    $this->ctl->addWarning("Afbeelding ontbreekt: {$img}");
                } else {
                    $images[] = $img_src;
                }
            }
            $product['image'] = array_shift($images);
            $this->ctl->db->query("
                DELETE FROM `" . DB_PREFIX . "product_image`
                WHERE product_id = {$productId}
            ");
            foreach($images as $i => $img) {
                Helpers::insert(
                    $this->ctl->db,
                    'product_image',
                    [
                        'product_id' => $productId,
                        'image'      => $img,
                        'sort_order' => $i + 1,
                    ]
                );
            }
        }

        if (isset($row[self::FIELD_QUANTITY])) {
            if ($row[self::FIELD_QUANTITY] != '') {
                $product['quantity'] = (int) $row[self::FIELD_QUANTITY];
                $product['subtract'] = 1;
            } else {
                $product['quantity'] = 999999;
                $product['subtract'] = 0;
            }
        }

        $this->findAndSet($row, $product, self::FIELD_STOCK_STATUS, 'stock_status', 'name', $this->defaultStockStatusId);
        $this->findAndSet($row, $product, self::FIELD_TAX_CLASS, 'tax_class', 'title', $this->defaultTaxClassId);

        $product['status'] = 0;
        if ($row[self::FIELD_STATUS] === null ||
            $row[self::FIELD_STATUS] === '' ||
            $row[self::FIELD_STATUS] === 'actief'
        ) {
            $product['status'] = 1;
        } elseif($row[self::FIELD_STATUS] !== 'inactief') {
            $this->ctl->addWarning("Ongeldige invoer in veld: " . self::FIELD_STATUS);
        }

        if (isset($row[self::FIELD_MANUFACTURER])) {
            $this->findAndSet($row, $product, self::FIELD_MANUFACTURER, 'manufacturer', 'name', 0);
        }

        if (isset($row[self::FIELD_CATEGORIES])) {
            $categoryIds = $this->findManyByTitle('category_description', 'category_id', 'name', Helpers::splitValues($row[self::FIELD_CATEGORIES]));
            $this->ctl->db->query("
                DELETE p2c
                FROM `" . DB_PREFIX . "product_to_category` p2c
                WHERE
                    p2c.product_id = " . (int) $productId . "
            ");
            foreach ($categoryIds as $categoryId) {
                Helpers::insert($this->ctl->db, 'product_to_category', [
                    'product_id' => $productId,
                    'category_id' => $categoryId,
                ]);
            }
        }

        if (isset($row[self::FIELD_FILTERS])) {
            $filterIds = $this->findFilters(Helpers::splitValues($row[self::FIELD_FILTERS]));
            foreach ($filterIds as $filterId) {
                Helpers::insert($this->ctl->db, 'product_to_filter', [
                    'product_id' => $productId,
                    'filter_id' => $filterId,
                ]);
            }
        }

        if (isset($row[self::FIELD_SORT_ORDER])) {
            if ($row[self::FIELD_SORT_ORDER] === '') {
                $product['sort_order'] = 0;
            } elseif (preg_match('/^-?[0-9]+$/', $row[self::FIELD_SORT_ORDER])) {
                $product['sort_order'] = (int) $row[self::FIELD_SORT_ORDER];
            } else {
                $product['sort_order'] = 0;
                $this->ctl->addWarning("Ongeldige invoer in veld: " . self::FIELD_SORT_ORDER);
            }
        }

        foreach ($this->getAttributes() as $attributeId => $label) {
            if (!isset($row[$label])) {
                continue;
            }

            $values = str_getcsv($row[$label], ';');
            $values = array_map('trim', $values);
            $values = array_filter($values, 'strlen');

            $this->ctl->db->query("
                DELETE FROM `" . DB_PREFIX . "product_attribute`
                WHERE
                    product_id = " . (int) $productId . "
                    AND attribute_id = " . (int) $attributeId . "
            ");

            foreach ($values as $value) {
                $attributeRow = [
                    'product_id' => $productId,
                    'attribute_id' => $attributeId,
                    'language_id' => $this->languageId,
                    'text' => $value,
                ];

                if ($preset = $this->findAttributePreset($attributeId, $value)) {
                    $attributeRow['text'] = $preset['text'];
                    $attributeRow['preset_id'] = $preset['preset_id'];
                }

                Helpers::insert($this->ctl->db, 'product_attribute', $attributeRow, ['update']);
            }
        }

        Helpers::update($this->ctl->db, 'product', ['product_id' => $productId], $product);

        Helpers::insert($this->ctl->db, 'product_description', $description, ['update']);
    }

    private function resolveImage($image) {
        $image = preg_replace('~[/\\\\]+~', '/', $image);
        $image = trim($image, '/');

        if(!$image) {
            return false;
        }

        $pfxs = array('catalog/', 'import/', '');

        foreach($pfxs as $pfx) {
            if(is_file(DIR_IMAGE . $pfx . $image)) {
                return $pfx . $image;
            }
        }

        return false;
    }

    private function findAndSet(ExportRow $row, array &$product, string $label, string $name, string $matchColumn, int $default) {
        if($row[$label] === null || $row[$label] === '') {
            $value = $default;
        } elseif(!($value = $this->findByTitle($name, "{$name}_id", $matchColumn, $row[$label]))) {
            $value = $default;
            $this->ctl->addWarning("Ongeldige invoer in veld: $label");
        }
        $product["{$name}_id"] = $value;
    }

    private function findManyByTitle($table, $col_id, $col_title, array $titles) {
        $result = array();
        foreach($titles as $title)
            if($id = $this->findByTitle($table, $col_id, $col_title, $title))
                $result[] = $id;
        return $result;
    }

    private function findFilters(array $values): array {
        $filters = array();

        foreach($values as $value) {
            $pair = preg_split('/:\s+/', $value, 2);

            if(sizeof($pair) == 1)
                $filter_id = $this->findByTitle(
                    'filter_description', 'filter_id', 'name', $pair[0]);
            else
                $filter_id = $this->findFilter($pair[0], $pair[1]);

            if($filter_id)
                $filters[] = $filter_id;
            else
                $this->ctl->addWarning("Filter niet gevonden: $value");
        }

        return $filters;
    }

    private function getAttributes() {
        if (isset($this->attributes)) {
            return $this->attributes;
        }

        $attributes = [];

        $result = $this->ctl->db->query("
            SELECT
                a.attribute_id,
                agd.name group_name,
                ad.name name
            FROM `" . DB_PREFIX . "attribute` a
            JOIN `" . DB_PREFIX . "attribute_description` ad ON
              ad.attribute_id = a.attribute_id
            JOIN `" . DB_PREFIX . "attribute_group` ag ON
              ag.attribute_group_id = a.attribute_group_id
            JOIN `" . DB_PREFIX . "attribute_group_description` agd ON
              agd.attribute_group_id = ag.attribute_group_id
            WHERE
                ad.language_id = " . (int) $this->languageId . "
                AND agd.language_id = " . (int) $this->languageId . "
        ");

        foreach ($result->rows as $row) {
            $attributes[(int)$row['attribute_id']] = Helpers::unescape($row['group_name']) . ': ' . Helpers::unescape($row['name']);
        }

        $this->attributes = $attributes;

        return $attributes;
    }

    private function findAttributePreset($attribute_id, $text) {
        if (!$this->attributePresetsEnabled) {
            return null;
        }

        if (!isset($this->attributePresets[$attribute_id])) {
            $this->attributePresets[$attribute_id] = $this->ctl->db->query("
                SELECT
                    ap.preset_id,
                    apd.text
                FROM `" . DB_PREFIX . "attribute_presets` ap
                JOIN `" . DB_PREFIX . "attribute_presets_description` apd USING (preset_id)
                WHERE
                    ap.attribute_id = " . (int) $attribute_id . "
                    AND apd.language_id = " . (int) $this->languageId . "
                ORDER BY
                    ap.preset_id
            ")->rows;
        }

        foreach ($this->attributePresets[$attribute_id] as $preset) {
            if (strcasecmp(trim($preset['text']), trim($text)) == 0) {
                return $preset;
            }
        }

        return null;
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

    private function findFilter($group_name, $filter_name) {
        $group_id = $this->findByTitle(
            'filter_group_description', 'filter_group_id', 'name',
            $group_name);

        if(!$group_id)
            return;

        $result = $this->ctl->db->query("
            SELECT f.filter_id
            FROM " . DB_PREFIX . "filter_description f
            WHERE
                f.name = '" . $this->ctl->db->escape($filter_name) . "'
                AND f.filter_group_id = " . (int) $group_id . "
            ORDER BY f.filter_id
            LIMIT 1
        ");

        if(!$result->row)
            return;

        return $result->row['filter_id'];
    }

    public function export(array $product): ExportRow {
        // LxBxH (cm)
        if(empty($product['length']) && empty($product['width']) && empty($product['height'])) {
            $dimensions = '';
        } else {
            $dimensions = sprintf(
                '%sx%sx%s',
                Helpers::formatNumber($product['length']),
                Helpers::formatNumber($product['width']),
                Helpers::formatNumber($product['height'])
            );
        }

        // Voorraadstatus
        if($product['stock_status_id']) {
            $result = $this->ctl->db->query("SELECT name FROM `" . DB_PREFIX . "stock_status` WHERE stock_status_id = {$product['stock_status_id']}");
            $stock_status = $result->row ? $result->row['name'] : '';
        } else {
            $stock_status = '';
        }

        // Belastinggroep
        if($product['tax_class_id']) {
            $result = $this->ctl->db->query("SELECT title FROM `" . DB_PREFIX . "tax_class` WHERE tax_class_id = {$product['tax_class_id']}");
            $tax_class = $result->row ? $result->row['title'] : '';
        } else {
            $tax_class = '';
        }

        // Afbeelding
        $images = array();

        if($product['image'])
            $images[] = $product['image'];

        $result = $this->ctl->db->query("
            SELECT image
            FROM `" . DB_PREFIX . "product_image`
            WHERE product_id = " . (int) $product['product_id'] . "
            ORDER BY sort_order, product_image_id
        ");
        foreach($result->rows as $row)
            $images[] = $row['image'];

        $images = implode("; ", $images);

        // Merk
        if($product['manufacturer_id']) {
            $result = $this->ctl->db->query("SELECT name FROM `" . DB_PREFIX . "manufacturer` WHERE manufacturer_id = '" . (int) $product['manufacturer_id'] . "'");
            $manufacturer = $result->row ? $result->row['name'] : '';
        } else {
            $manufacturer = '';
        }

        // Categorieën
        $result = $this->ctl->db->query("
                SELECT cd.name
                FROM `" . DB_PREFIX . "product_to_category` ptc
                INNER JOIN `" . DB_PREFIX . "category` c ON
                    c.category_id = ptc.category_id
                INNER JOIN `" . DB_PREFIX . "category_description` cd ON
                    cd.category_id = c.category_id
                    AND cd.language_id = '" . (int) $this->languageId . "'
                WHERE
                    ptc.product_id = " . (int) $product['product_id'] . "
                ORDER BY
                    c.category_id
            ");
        $categories = array();
        foreach($result->rows as $row)
            $categories[] = Helpers::unescape($row['name']);
        $categories = implode("; ", $categories);

        // Filters
        $result = $this->ctl->db->query("
                SELECT
                    fgd.name group_name,
                    fd.name
                FROM `" . DB_PREFIX . "product_filter` pf
                INNER JOIN `" . DB_PREFIX . "filter` f ON
                    f.filter_id = pf.filter_id
                INNER JOIN `" . DB_PREFIX . "filter_description` fd ON
                    fd.filter_id = f.filter_id
                    AND fd.language_id = " .
            (int) $this->languageId . "
                INNER JOIN `" . DB_PREFIX . "filter_group` fg ON
                    fg.filter_group_id = f.filter_group_id
                INNER JOIN `" . DB_PREFIX . "filter_group_description` fgd ON
                    fgd.filter_group_id = fg.filter_group_id
                    AND fgd.language_id = " .
            (int) $this->languageId . "
                WHERE
                    pf.product_id = " . (int) $product['product_id'] . "
                ORDER BY
                    fg.sort_order,
                    f.sort_order
            ");
        $filters = array();
        foreach($result->rows as $row)
            $filters[] = sprintf('%s: %s',
                Helpers::unescape($row['group_name']),
                Helpers::unescape($row['name']));
        $filters = implode("; ", $filters);

        // Attributen
        $result = $this->ctl->db->query("
            SELECT
                pa.attribute_id,
                pa.text
            FROM `" . DB_PREFIX . "product_attribute` pa
            WHERE
                pa.product_id = " . (int) $product['product_id'] . "
                AND pa.language_id = " . (int) $this->languageId . "
            ORDER BY
                pa.text
        ");
        $attribute_labels = $this->getAttributes();
        $product_attributes = array();
        foreach($result->rows as $row) {
            if (isset($attribute_labels[$row['attribute_id']])) {
                $product_attributes[$attribute_labels[$row['attribute_id']]][] = trim(Helpers::unescape($row['text']));
            }
        }
        $product_attributes = array_map(function ($values) {
            return implode('; ', $values);
        }, $product_attributes);

        // Add product row.
        return (new ExportRow())
            ->addField(1000, 'ID', $product['product_id'])
            ->addField(1020, self::FIELD_NAME, Helpers::unescape($product['name']))
            ->addField(1030, self::FIELD_PRICE, Helpers::unescape($product['price']))
            ->addField(1050, self::FIELD_QUANTITY, $product['quantity'])
            ->addField(1060, self::FIELD_STOCK_STATUS, $stock_status)
            ->addField(1070, self::FIELD_WEIGHT, empty($product['weight']) ? '' : Helpers::unescape($product['weight']))
            ->addField(1080, self::FIELD_DIMENSIONS, $dimensions)
            ->addField(1090, self::FIELD_DESCRIPTION, Helpers::unescape($product['description']))
            ->addField(1100, self::FIELD_META_TITLE, Helpers::unescape($product['meta_title']))
            ->addField(1110, self::FIELD_META_DESCRIPTION, Helpers::unescape($product['meta_description']))
            ->addField(1120, self::FIELD_META_KEYWORD, Helpers::unescape($product['meta_keyword']))
            ->addField(1130, self::FIELD_TAGS, Helpers::unescape($product['tag']))
            ->addField(1140, self::FIELD_IMAGES, $images)
            ->addField(1150, self::FIELD_MODEL, $product['name'] == $product['model'] ? '' : Helpers::unescape($product['model']))
            ->addField(1160, self::FIELD_SKU, Helpers::unescape($product['sku']))
            ->addField(1170, self::FIELD_UPC, Helpers::unescape($product['upc']))
            ->addField(1180, self::FIELD_EAN, Helpers::unescape($product['ean']))
            ->addField(1190, self::FIELD_JAN, Helpers::unescape($product['jan']))
            ->addField(1200, self::FIELD_ISBN, Helpers::unescape($product['isbn']))
            ->addField(1210, self::FIELD_MPN, Helpers::unescape($product['mpn']))
            ->addField(1220, self::FIELD_LOCATION, Helpers::unescape($product['location']))
            ->addField(1230, self::FIELD_TAX_CLASS, $tax_class)
            ->addField(1240, self::FIELD_STATUS, $product['status'] ? 'actief' : 'inactief')
            ->addField(1250, self::FIELD_SORT_ORDER, $product['sort_order'] == '0' ? '' : $product['sort_order'])
            ->addField(1260, self::FIELD_MANUFACTURER, $manufacturer)
            ->addField(1270, self::FIELD_CATEGORIES, $categories)
            ->addField(1280, self::FIELD_FILTERS, $filters)
            ->addFields(1290, $product_attributes);
    }

}