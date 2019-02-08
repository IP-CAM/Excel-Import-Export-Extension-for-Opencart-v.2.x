<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once DIR_SYSTEM . 'library/phpexcel/Classes/PHPExcel.php';
require_once DIR_SYSTEM . 'helper/kiboimex.php';

if (file_exists(DIR_SYSTEM . 'library/unic/unic.php')) {
    require_once DIR_SYSTEM . 'library/unic/unic.php';
}

class ControllerCatalogKiboimex extends Controller {
    private $data;
    private $re_number = '[0-9]+(?:[,.][0-9]+)?';
    private $attributes = [];
    private $hasCalculator;
    private $calculatorVariables = [];

    public function index() {
        $this->load->model('catalog/product');

        $this->_initializeDatabase();

        $this->data = $this->_getData();

        $this->hasCalculator = $this->tableExists(DB_PREFIX . 'unic_calc');

        if(!empty($_FILES['import']))
            $this->_doImport();
        elseif(!empty($_POST['export']))
            $this->_doExport();

        $result = $this->db->query("SELECT COUNT(*) n FROM `" . DB_PREFIX . "product` WHERE kiboimex_imported = 1");
        $this->data['imported_count'] = $result->row['n'];

        $this->response->setOutput($this->renderView('catalog/kiboimex.tpl', $this->data));
    }

    private function tableExists($table) {
        $result = $this->db->query('SHOW TABLES');

        foreach ($result->rows as $row) {
            if ($table == array_shift($row)) {
                return true;
            }
        }

        return false;
    }

    private function renderView($__template, array $__data) {
        extract($__data);
        ob_start();
        include DIR_TEMPLATE . $__template;
        return ob_get_clean();
    }

    private function _initializeDatabase() {
        $result = $this->db->query("DESC `" . DB_PREFIX . "product`");

        foreach ($result->rows as $row) {
            if (array_shift($row) == 'kiboimex_imported') {
                return;
            }
        }

        $queries = array(
            "ALTER TABLE `" . DB_PREFIX . "product` ADD kiboimex_imported TINYINT(1) NOT NULL DEFAULT 0",
        );

        foreach ($queries as $query) {
            $this->db->query($query);
        }
    }

    private function _doImport() {
        $upload = $_FILES['import'];

        // Handle file upload
        if(!isset($upload['error']) || is_array($upload['error']))
            return $this->_addError("Er zijn meerdere bestanden geüpload. Probeer het opnieuw.");

        switch($upload['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return $this->_addError("Er is geen bestand geüpload. Probeer het opnieuw.");
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return $this->_addError("Het bestand is te groot.");
            default:
                return $this->_addError("Er is een onbekende fout opgetreden. Foutcode: {$upload['error']}.");
        }

        // Try to open XLS file.
        try {
            $rows = $this->_excelFileToArray($upload['tmp_name']);
        } catch(RuntimeError $e) {
            return $this->_addError($e->getMessage());
        }

        // Remove empty rows.
        $rows = array_filter($rows, function($row) {
            return array_filter($row, 'strlen');
        });

        // Check sheet length.
        if(sizeof($rows) < 2)
            return $this->_addError("Dit bestand is leeg. Controleer het bestand in Excel.");

        // Check header.
        $header_row = array_keys($rows);
        $header_row = $header_row[0];
        $header = $rows[$header_row];
        unset($rows[$header_row]);
        $required = array(
            'ID',
            'Productnaam',
            'Gewicht (kg)',
            'LxBxH (cm)',
            'Prijs',
            'Um',
            'Omschrijving',
            'Meta Tag Titel',
            'Meta Tag Omschrijving',
            'Meta Tag Zoekwoorden',
            'Product Tags',
            'Afbeelding',
            'Model',
            'SKU',
            'UPC',
            'EAN',
            'JAN',
            'ISBN',
            'MPN',
            'Locatie',
            'Belastinggroep',
            'Status',
            'Merk',
            'Categorieën',
            'Filters',
        );
        $missing = array_diff($required, $header);
        if($missing)
            return $this->_addError("De volgende velden ontbreken: " . implode(", ", $missing) . ".");

        // Get language ID.
        $result = $this->db->query("
            SELECT language_id
            FROM `" . DB_PREFIX . "language`
            WHERE status = 1
            ORDER BY sort_order
            LIMIT 1
        ");
        if(!$result->row)
            return $this->_addError("Er is geen taal ingesteld. Controleer de shopinstellingen.");
        $language_id = $result->row['language_id'];

        // Get default tax class ID.
        $result = $this->db->query("
            SELECT tax_class_id
            FROM `" . DB_PREFIX . "tax_class`
            ORDER BY tax_class_id
            LIMIT 1
        ");
        $default_tax_class_id = $result->row ? $result->row['tax_class_id'] : '';

        // Get default stock status ID.
        $result = $this->db->query("
            SELECT stock_status_id
            FROM `" . DB_PREFIX . "stock_status`
            WHERE
              language_id = {$language_id}
              AND name IN ('In Stock', 'Op voorraad')
            ORDER BY stock_status_id
            LIMIT 1
        ");
        $default_stock_status_id = $result->row ? $result->row['stock_status_id'] : '';

        // Get default weight class ID.
        $result = $this->db->query("
            SELECT weight_class_id
            FROM `" . DB_PREFIX . "weight_class`
            WHERE value = 1.0
            ORDER BY weight_class_id
            LIMIT 1
        ");
        if(!$result->row)
            return $this->_addError("Er is geen gewichtseenheid met waarde 1.0 gedefinieerd. Pas de shopinstellingen aan.");
        $default_weight_class_id = $result->row['weight_class_id'];

        // Get default length class ID.
        $result = $this->db->query("
            SELECT length_class_id
            FROM `" . DB_PREFIX . "length_class`
            WHERE value = 1.0
            ORDER BY length_class_id
            LIMIT 1
        ");
        if(!$result->row)
            return $this->_addError("Er is geen lengte-eenheid met waarde 1.0 gedefinieerd. Pas de shopinstellingen aan.");
        $default_length_class_id = $result->row['length_class_id'];

        // Convert rows into products.
        $header = array_map('strval', $header);
        $header_flipped = array_reverse(array_flip(array_reverse($header)));
        $products = array();
        foreach($rows as $n => $row) {
            $data = array();
            foreach($header_flipped as $k => $v)
                $data[$k] = isset($row[$v]) ? trim($row[$v]) : '';

            // Defaults
            $product['row'] = $n;
            $product['product']['weight_class_id'] = $default_weight_class_id;
            $product['product']['length_class_id'] = $default_length_class_id;
            $product['description']['language_id'] = $language_id;

            // Text fields
            $map = array(
                'Productnaam' => 'description.name',
                'Omschrijving' => 'description.description',
                'Meta Tag Titel' => 'description.meta_title',
                'Meta Tag Omschrijving' => 'description.meta_description',
                'Meta Tag Zoekwoorden' => 'description.meta_keyword',
                'Product Tags' => 'description.tag',
                'Model' => 'product.model',
                'Um' => 'product.um',
                'SKU' => 'product.sku',
                'UPC' => 'product.upc',
                'EAN' => 'product.ean',
                'JAN' => 'product.jan',
                'ISBN' => 'product.isbn',
                'MPN' => 'product.mpn',
                'Locatie' => 'product.location',
            );

            foreach($map as $field => $set) {
                list($tbl, $col) = explode('.', $set);
                $product[$tbl][$col] = $data[$field];
            }

            if(strlen($product['description']['meta_title']) == 0)
                $product['description']['meta_title'] = $product['description']['name'];

            if(strlen($product['product']['model']) == 0)
                $product['product']['model'] = $product['description']['name'];

            // ID
            if($data['ID'] == '') {
                $product['product']['product_id'] = 0;
            } elseif(preg_match('/^[0-9]+$/', $data['ID']) && $data['ID'] > 0) {
                $product['product']['product_id'] = $data['ID'];
            } else {
                $product['product']['product_id'] = 0;
                $this->_addWarning($n, "Ongeldige invoer in veld: ID");
            }

            // Prijs
            if($this->_parseNumber($data['Prijs']) === false) {
                $this->_addWarning($n, "Ongeldige invoer in veld: Prijs");
                $product['product']['price'] = 0.0;
            } else {
                $product['product']['price'] = $this->_parseNumber($data['Prijs']);
            }

            // LxBxH (cm)
            if(empty($data['LxBxH (cm)'])) {
                $product['product']['length'] = 0.0;
                $product['product']['width'] = 0.0;
                $product['product']['height'] = 0.0;
            } elseif(preg_match("~^({$this->re_number})x({$this->re_number})x({$this->re_number})$~i", $data['LxBxH (cm)'], $match)) {
                $product['product']['length'] = $this->_parseNumber($match[1]);
                $product['product']['width'] = $this->_parseNumber($match[2]);
                $product['product']['height'] = $this->_parseNumber($match[3]);
            } else {
                $this->_addWarning($n, "Ongeldige invoer in veld: LxBxH (cm)");
                $product['product']['length'] = 0.0;
                $product['product']['width'] = 0.0;
                $product['product']['height'] = 0.0;
            }

            // Afbeelding
            $images = array();
            foreach($this->_splitValues($data['Afbeelding']) as $img) {
                $img_src = $this->_resolveImage($img);
                if(!$img_src)
                    $this->_addWarning($n, "Afbeelding ontbreekt: {$img}");
                else
                    $images[] = $img_src;
            }
            $product['product']['image'] = $images ? array_shift($images) : '';
            $product['image'] = array();
            foreach($images as $i => $img)
                $product['image'][] = array(
                    'image'      => $img,
                    'sort_order' => $i + 1,
                );

            // Voorraad
            if (isset($data['Voorraad']) && $data['Voorraad'] != '') {
                $product['product']['quantity'] = (int) $data['Voorraad'];
                $product['product']['subtract'] = 1;
            } else {
                $product['product']['quantity'] = 999999;
                $product['product']['subtract'] = 0;
            }

            // Voorraadstatus
            if($data['Voorraadstatus'] == '')
                $stock_status_id = $default_stock_status_id;
            elseif(!($stock_status_id = $this->_findByTitle('stock_status', 'stock_status_id', 'name', $data['Voorraadstatus'])))
                $this->_addWarning($n, "Ongeldige invoer in veld: Voorraadstatus");
            $product['product']['stock_status_id'] = $stock_status_id;

            // Belastinggroep
            if($data['Belastinggroep'] == '')
                $tax_class_id = $default_tax_class_id;
            elseif(!($tax_class_id = $this->_findByTitle('tax_class', 'tax_class_id', 'title', $data['Belastinggroep'])))
                $this->_addWarning($n, "Ongeldige invoer in veld: Belastinggroep");
            $product['product']['tax_class_id'] = $tax_class_id;

            // Status
            $product['product']['status'] = 0;
            if($data['Status'] == '' || $data['Status'] == 'actief')
                $product['product']['status'] = 1;
            elseif($data['Status'] != 'inactief')
                $this->_addWarning($n, "Ongeldige invoer in veld: Status");

            // Merk
            if($data['Merk'] == '')
                $product['product']['manufacturer_id'] = '';
            elseif(!($product['product']['manufacturer_id'] =
                     $this->_findByTitle('manufacturer', 'manufacturer_id', 'name', $data['Merk'])))
                $this->_addWarning($n, "Ongeldige invoer in veld: Merk");

            // Categorieën
            $product['to_category'] = array();
            foreach($this->_findManyByTitle('category_description', 'category_id', 'name',
                                            $this->_splitValues($data['Categorieën'])) as $cat_id)
                $product['to_category'][] = array('category_id' => $cat_id);

            // Filters
            $product['filter'] =
                $this->_findFilters($n, $this->_splitValues($data['Filters']));

            // Sort order
            $product['product']['sort_order'] = 0;
            if(!isset($data['Sorteervolgorde']) || $data['Sorteervolgorde'] == '');
            elseif(!preg_match('/^-?[0-9]+$/', $data['Sorteervolgorde']))
                $this->_addWarning($n, "Ongeldige invoer in veld: Sorteervolgorde");
            else
                $product['product']['sort_order'] = (int) $data['Sorteervolgorde'];

            // Calculator
            if ($this->hasCalculator) {
                // Calculator selection
                if (!isset($data['Calculator']) || $data['Calculator'] == '')
                    $product['product']['unic_calc_id'] = null;
                elseif (!($product['product']['unic_calc_id'] =
                          $this->_findByTitle('unic_calc', 'id', 'label', $data['Calculator'])))
                    $this->_addWarning($n, "Ongeldige invoer in veld: Calculator");

                // Variables
                $product['unic_product_var'] = [];
                foreach ($this->_getCalculatorVariables() as $calculatorVariable) {
                    if (!empty($data[$calculatorVariable['label']])) {
                        $product['unic_product_var'][] = [
                            'var_id' => $calculatorVariable['id'],
                            'type' => $calculatorVariable['type'],
                            'value' => $data[$calculatorVariable['label']],
                        ];
                    }
                }
            }

            // Attributen
            $extra_attributes = $this->_getExtraAttributes();
            $product['attribute'] = [];
            foreach ($extra_attributes as $attr_id => $label) {
                if (isset($data[$label])) {
                    $product['attribute'][] = [
                        'attribute_id' => $attr_id,
                        'text' => $data[$label],
                    ];
                }
            }

            $products[] = $product;
        }

        // Insert/update products.
        $this->data['inserted'] = 0;
        $this->data['updated'] = 0;
        $this->data['deleted'] = 0;

        if(!empty($_POST['delete_imported'])) {
            $result = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE kiboimex_imported = 1");
            foreach($result->rows as $row)
                $this->model_catalog_product->deleteProduct($row['product_id']);
            $this->data['deleted'] = sizeof($result->rows);
        }

        foreach($products as $product)
            $this->_saveProduct($product);

        $this->cache->delete('product');

        try {
            $this->load->model('module/brainyfilter');
        } catch (Throwable $e) {}
        if (class_exists(ModelModuleBrainyFilter::class)) {
            (new ModelModuleBrainyFilter($this->registry))->cacheProductProperties();
        }

        $this->data['success'] = true;
    }

    private function _addError($message) {
        $this->data['errors'][] = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }

    private function _addWarning($row, $message) {
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $this->data['warnings'][$message][] = $row;
    }

    private function _excelFileToArray($filename) {
        $excel = PHPExcel_IOFactory::load($filename);
        return $excel->getActiveSheet()->toArray(null, true, false, true);
    }

    private function _splitValues($str) {
        $values = preg_split('/;/', $str);
        $values = array_unique(array_map('trim', $values));
        $values = array_filter($values, 'strlen');
        return $values;
    }

    private function _findByTitle($table, $col_id, $col_title, $title) {
        $result = $this->db->query("
            SELECT `{$col_id}` AS id
            FROM `" . DB_PREFIX . "{$table}`
            WHERE `{$col_title}` = '" . $this->db->escape($title) . "'
            ORDER BY `{$col_id}`
        ");
        return $result->row ? $result->row['id'] : '';
    }

    private function _findManyByTitle($table, $col_id, $col_title, array $titles) {
        $result = array();
        foreach($titles as $title)
            if($id = $this->_findByTitle($table, $col_id, $col_title, $title))
                $result[] = $id;
        return $result;
    }

    private function _findFilters($n, array $values) {
        $filters = array();

        foreach($values as $value) {
            $pair = preg_split('/:\s+/', $value, 2);

            if(sizeof($pair) == 1)
                $filter_id = $this->_findByTitle(
                    'filter_description', 'filter_id', 'name', $pair[0]);
            else
                $filter_id = $this->_findFilter($pair[0], $pair[1]);

            if($filter_id)
                $filters[] = array('filter_id' => $filter_id);
            else
                $this->_addWarning($n, "Filter niet gevonden: $value");
        }

        return $filters;
    }

    private function _findFilter($group_name, $filter_name) {
        $group_id = $this->_findByTitle(
            'filter_group_description', 'filter_group_id', 'name',
            $group_name);

        if(!$group_id)
            return;

        $result = $this->db->query("
            SELECT f.filter_id
            FROM " . DB_PREFIX . "filter_description f
            WHERE
                f.name = '" . $this->db->escape($filter_name) . "'
                AND f.filter_group_id = " . (int) $group_id . "
            ORDER BY f.filter_id
            LIMIT 1
        ");

        if(!$result->row)
            return;

        return $result->row['filter_id'];
    }

    private function _resolveImage($image) {
        $image = str_replace('\\', '/', $image);
        $image = preg_replace('~//+~', '/', $image);
        $image = trim($image, '/');

        if(!$image)
            return false;

        $pfxs = array('catalog/', 'import/', '');

        foreach($pfxs as $pfx)
            if(is_file(DIR_IMAGE . $pfx . $image))
                return $pfx . $image;

        return false;
    }

    private function _saveProduct(array $product) {
        $this->db->query("BEGIN;");

        // Check if product exists.
        if($product['product']['product_id']) {
            $result = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product`
                                        WHERE product_id = " . (int) $product['product']['product_id']);
            $product_id = $result->row ? $result->row['product_id'] : 0;
        } else {
            $product_id = 0;
        }

        $insert = !$product_id;

        // Insert or update product.
        $row = $this->_escapeArray($product['product']);
        unset($row['product_id']);

        $row['kiboimex_imported'] = 1;

        if($product_id)
            $this->_update('product', array('product_id' => $product_id), $row);
        else
            $product_id = $this->_insert('product', $row);

        // Add product to default store.
        $this->_insert('product_to_store', array('product_id' => $product_id, 'store_id' => 0), array('ignore'));

        // Save description.
        $row = array_merge($product['description'], array(
            'product_id' => $product_id,
        ));
        $row = $this->_escapeArray($row);
        $this->_insert('product_description', $row, array('update'));

        // Save images.
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_image` WHERE product_id = {$product_id}");
        foreach($product['image'] as $image)
            $this->_insert('product_image', array_merge($image, array('product_id' => $product_id)));

        // Save category associations.
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = {$product_id}");
        foreach($product['to_category'] as $row)
            $this->_insert('product_to_category', array_merge($row, array('product_id' => $product_id)));

        // Save filter associations.
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_filter` WHERE product_id = {$product_id}");
        foreach($product['filter'] as $row)
            $this->_insert('product_filter', array_merge($row, array('product_id' => $product_id)));

        // Save calculator variables.
        if ($this->hasCalculator && !empty($product['unic_product_var'])) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "unic_product_var` WHERE product_id = {$product_id}");
            foreach ($product['unic_product_var'] as $i => $row) {
                $value = Unic::clean($row['value'], $row['type']);
                $this->_insert('unic_product_var', [
                    'var_id' => $row['var_id'],
                    'product_id' => $product_id,
                    'value' => $value,
                ]);
            }
        }

        // Save product attributes.
        if (!empty($product['attribute'])) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "product_attribute` WHERE product_id = {$product_id}");
            foreach ($product['attribute'] as $row) {
                if ($row['text'] === '') {
                    continue;
                }
                $this->_insert('product_attribute', array_merge($row, array(
                    'product_id' => $product_id,
                    'language_id' => (int)$this->config->get('config_language_id'))
                ));
            }
        }

        $this->db->query("COMMIT");

        $this->data[$insert ? 'inserted' : 'updated']++;
    }

    private function _insert($table, array $row, array $opt = array()) {
        $verb = in_array('ignore', $opt) ? "INSERT IGNORE" : "INSERT";
        $sql = "{$verb} INTO `" . DB_PREFIX . "{$table}` SET {$this->_makeSet($row)}";
        if(in_array('update', $opt))
            $sql .= " ON DUPLICATE KEY UPDATE {$this->_makeSet($row)}";
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    private function _update($table, array $where, array $row) {
        $this->db->query($sql="UPDATE `" . DB_PREFIX . "{$table}` SET {$this->_makeSet($row)} WHERE {$this->_makeWhere($where)}");
    }

    private function _makeSet(array $row) {
        $set = array();
        foreach($row as $k => $v)
            $set[] = "`$k` = {$this->_quote($v)}";
        return implode(", ", $set);
    }

    private function _makeWhere(array $row) {
        $where = array();
        foreach($row as $k => $v)
            $where[] = "`$k` = {$this->_quote($v)}";
        return implode(" AND ", $where);
    }

    private function _quote($v) {
        if($v === null)
            return "NULL";
        else
            return "'{$this->db->escape($v)}'";
    }

    private function _escape($str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    private function _unescape($str) {
        return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }

    private function _escapeArray(array $arr) {
        foreach($arr as $k => $v) {
            if($v === null) continue;
            $arr[$k] = $this->_escape($v);
        }
        return $arr;
    }

    private function _getCalculatorVariables()
    {
        if ($this->hasCalculator && !$this->calculatorVariables) {
            $result = $this->db->query(
                "SELECT uv.id, uv.type, uc.label calc_label, uv.label var_label
                    FROM `" . DB_PREFIX . "unic_var` uv
                    JOIN `" . DB_PREFIX . "unic_calc` uc ON
                        uc.id = uv.calc_id");

            foreach ($result->rows as $row) {
                $this->calculatorVariables[] = [
                    'id' => $row['id'],
                    'type' => $row['type'],
                    'label' => $row['calc_label'] . ': ' . $row['var_label']
                ];
            }
        }

        return $this->calculatorVariables;
    }

    private function _getExtraAttributes()
    {
        if (!$this->attributes) {
            $attributes = [];
            $result = $this->db->query("SELECT
                a.attribute_id, agd.name AS attr_group_name, ad.name AS attr_name
                FROM `" . DB_PREFIX . "attribute` a
                JOIN `" . DB_PREFIX . "attribute_description` ad ON
                  ad.attribute_id = a.attribute_id
                JOIN `" . DB_PREFIX . "attribute_group` ag ON
                  ag.attribute_group_id = a.attribute_group_id
                JOIN `" . DB_PREFIX . "attribute_group_description` agd ON
                  agd.attribute_group_id = ag.attribute_group_id
                WHERE
                    ad.language_id = " . (int)$this->config->get('config_language_id') . "
                    AND agd.language_id = " . (int)$this->config->get('config_language_id') . "
            ");

            foreach ($result->rows as $row) {
                $attributes[(int)$row['attribute_id']] = $this->_unescape($row['attr_group_name']) . ': ' . $this->_unescape($row['attr_name']);
            }

            $this->attributes = $attributes;
        }

        return $this->attributes;
    }

    private function _doExport() {
        $fields = array(
            'ID',
            'Productnaam',
            'Prijs',
            'Um',
            'Voorraad',
            'Voorraadstatus',
            'Gewicht (kg)',
            'LxBxH (cm)',
            'Omschrijving',
            'Meta Tag Titel',
            'Meta Tag Omschrijving',
            'Meta Tag Zoekwoorden',
            'Product Tags',
            'Afbeelding',
            'Model',
            'SKU',
            'UPC',
            'EAN',
            'JAN',
            'ISBN',
            'MPN',
            'Locatie',
            'Belastinggroep',
            'Status',
            'Sorteervolgorde',
            'Merk',
            'Categorieën',
            'Filters',
            'Opties',
        );

        if ($this->hasCalculator) {
            $fields[] = 'Calculator';

            foreach ($this->_getCalculatorVariables() as $calculatorVariable) {
                $fields[] = $calculatorVariable['label'];
            }
        }

        // Extra attribute groups
        $fields = array_merge($fields, $this->_getExtraAttributes());

        $rows = array();
        $rows[] = array_combine($fields, $fields);

        $result = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` ORDER BY product_id");
        foreach($result->rows as $row) {
            $product_id = $row['product_id'];

            // Get product info.
            $product = $this->model_catalog_product->getProduct($product_id);

            if (!$product) {
                continue;
            }

            // LxBxH (cm)
            if(empty($product['length']) && empty($product['width']) && empty($product['height'])) {
                $dimensions = '';
            } else {
                $dimensions = sprintf(
                    '%sx%sx%s',
                    $this->_formatNumber($product['length']),
                    $this->_formatNumber($product['width']),
                    $this->_formatNumber($product['height'])
                );
            }

            // Voorraadstatus
            if($product['stock_status_id']) {
                $result = $this->db->query("SELECT name FROM `" . DB_PREFIX . "stock_status` WHERE stock_status_id = {$product['stock_status_id']}");
                $stock_status = $result->row ? $result->row['name'] : '';
            } else {
                $stock_status = '';
            }

            // Belastinggroep
            if($product['tax_class_id']) {
                $result = $this->db->query("SELECT title FROM `" . DB_PREFIX . "tax_class` WHERE tax_class_id = {$product['tax_class_id']}");
                $tax_class = $result->row ? $result->row['title'] : '';
            } else {
                $tax_class = '';
            }

            // Afbeelding
            $images = array();

            if($product['image'])
                $images[] = $product['image'];

            $result = $this->db->query("SELECT image FROM `" . DB_PREFIX . "product_image` WHERE product_id = {$product_id} ORDER BY sort_order, product_image_id");
            foreach($result->rows as $row)
                $images[] = $row['image'];

            $images = implode("; ", $images);

            // Merk
            if($product['manufacturer_id']) {
                $result = $this->db->query("SELECT name FROM `" . DB_PREFIX . "manufacturer` WHERE manufacturer_id = '" . (int) $product['manufacturer_id'] . "'");
                $manufacturer = $result->row ? $result->row['name'] : '';
            } else {
                $manufacturer = '';
            }

            // Categorieën
            $result = $this->db->query("
                SELECT cd.name
                FROM `" . DB_PREFIX . "product_to_category` ptc
                INNER JOIN `" . DB_PREFIX . "category` c ON
                    c.category_id = ptc.category_id
                INNER JOIN `" . DB_PREFIX . "category_description` cd ON
                    cd.category_id = c.category_id
                    AND cd.language_id = '" . (int) $this->config->get('config_language_id') . "'
                WHERE
                    ptc.product_id = {$product_id}
                ORDER BY
                    c.category_id
            ");
            $categories = array();
            foreach($result->rows as $row)
                $categories[] = $this->_unescape($row['name']);
            $categories = implode("; ", $categories);

            // Filters
            $result = $this->db->query("
                SELECT
                    fgd.name group_name,
                    fd.name
                FROM `" . DB_PREFIX . "product_filter` pf
                INNER JOIN `" . DB_PREFIX . "filter` f ON
                    f.filter_id = pf.filter_id
                INNER JOIN `" . DB_PREFIX . "filter_description` fd ON
                    fd.filter_id = f.filter_id
                    AND fd.language_id = " .
                        (int) $this->config->get('config_language_id') . "
                INNER JOIN `" . DB_PREFIX . "filter_group` fg ON
                    fg.filter_group_id = f.filter_group_id
                INNER JOIN `" . DB_PREFIX . "filter_group_description` fgd ON
                    fgd.filter_group_id = fg.filter_group_id
                    AND fgd.language_id = " .
                        (int) $this->config->get('config_language_id') . "
                WHERE
                    pf.product_id = {$product_id}
                ORDER BY
                    fg.sort_order,
                    f.sort_order
            ");
            $filters = array();
            foreach($result->rows as $row)
                $filters[] = sprintf('%s: %s',
                                     $this->_unescape($row['group_name']),
                                     $this->_unescape($row['name']));
            $filters = implode("; ", $filters);

            // Calculator variables
            if ($this->hasCalculator) {
                $calculator_variables = [];
                $result = $this->db->query(
                    "SELECT upv.product_id, upv.var_id, uv.type, uv.name, uc.label calc_label, uv.label var_label, upv.value
                    FROM `" . DB_PREFIX . "unic_product_var` upv
                    JOIN `" . DB_PREFIX . "unic_var` uv ON uv.id = upv.var_id
                    JOIN `" . DB_PREFIX . "unic_calc` uc ON uv.calc_id = uc.id
                    WHERE
                        upv.product_id = {$product_id}
                        AND uc.id = " . (int) $product['unic_calc_id'] . "
                    ORDER BY uv.sort");

                if (isset($result->rows)) {
                    $calculator_variables = $result->rows;
                }
            }

            // Attributen
            $result = $this->db->query("SELECT
                agd.name AS attr_group_name, ad.name AS attr_name, pa.text
                FROM
                  `" . DB_PREFIX . "product_attribute` pa
                JOIN `" . DB_PREFIX . "attribute` a ON
                  a.attribute_id = pa.attribute_id
                JOIN `" . DB_PREFIX . "attribute_description` ad ON
                  ad.attribute_id = a.attribute_id
                JOIN `" . DB_PREFIX . "attribute_group` ag ON
                  ag.attribute_group_id = a.attribute_group_id
                JOIN `" . DB_PREFIX . "attribute_group_description` agd ON
                  agd.attribute_group_id = ag.attribute_group_id
                WHERE
                    pa.product_id = {$product_id}
                    AND pa.language_id = " . (int) $this->config->get('config_language_id') . "
                    AND ad.language_id = " . (int) $this->config->get('config_language_id') . "
                    AND agd.language_id = " . (int) $this->config->get('config_language_id') . "
            ");
            $product_attributes = array();
            foreach($result->rows as $row) {
                $product_attributes[$this->_unescape($row['attr_group_name']) . ': ' . $this->_unescape($row['attr_name'])] = $this->_unescape($row['text']);
            }

            // Add product row.
            $row = array(
                'ID' => $product['product_id'],
                'Productnaam' => $this->_unescape($product['name']),
                'Prijs' => $this->_unescape($product['price']),
                'Um' => $this->_unescape($product['um']),
                'Voorraad' => $product['quantity'],
                'Voorraadstatus' => $stock_status,
                'Gewicht (kg)' => empty($product['weight']) ? '' : $this->_unescape($product['weight']),
                'LxBxH (cm)' => $dimensions,
                'Omschrijving' => $this->_unescape($product['description']),
                'Meta Tag Titel' => $this->_unescape($product['meta_title']),
                'Meta Tag Omschrijving' => $this->_unescape($product['meta_description']),
                'Meta Tag Zoekwoorden' => $this->_unescape($product['meta_keyword']),
                'Product Tags' => $this->_unescape($product['tag']),
                'Afbeelding' => $images,
                'Model' => $product['name'] == $product['model'] ? '' : $this->_unescape($product['model']),
                'SKU' => $this->_unescape($product['sku']),
                'UPC' => $this->_unescape($product['upc']),
                'EAN' => $this->_unescape($product['ean']),
                'JAN' => $this->_unescape($product['jan']),
                'ISBN' => $this->_unescape($product['isbn']),
                'MPN' => $this->_unescape($product['mpn']),
                'Locatie' => $this->_unescape($product['location']),
                'Belastinggroep' => $tax_class,
                'Status' => $product['status'] ? 'actief' : 'inactief',
                'Sorteervolgorde' => $product['sort_order'] == '0' ? '' : $product['sort_order'],
                'Merk' => $manufacturer,
                'Categorieën' => $categories,
                'Filters' => $filters,
            );

            if ($this->hasCalculator) {
                foreach ($calculator_variables as $calc_var) {
                    $row['Calculator'] = $calc_var['calc_label'];
                    $value = $calc_var['value'];
                    if ($calc_var['type'] == 'boolean') {
                        $value = $value ? 'ja' : '';
                    }
                    $row[$calc_var['calc_label'] . ': ' . $calc_var['var_label']] = $value;
                }
            }

            foreach ($fields as $field) {
                if (isset($product_attributes[$field])) {
                    $row[$field] = $product_attributes[$field];
                }
            }

            $rows[] = $row;
        }

        // Generate Excel sheet.
        $excel = new PHPExcel;
        $sheet = $excel->setActiveSheetIndex(0);

        $fields_flip = array_flip($fields);
        foreach($rows as $i => $row) {
            foreach($row as $k => $v) {
                if(!isset($fields_flip[$k]))
                    continue;
                if($v === null || $v === '')
                    continue;
                $sheet->setCellValueByColumnAndRow($fields_flip[$k], $i+1, $v);
            }
        }

        $fp = fopen('php://memory', 'w+');

        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
        $writer->save($fp);

        if(headers_sent() || ob_get_length())
            exit;

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="export-' . date('Y-m-d\\TH:i:s') . '.xls"');

        fseek($fp, 0);
        stream_copy_to_stream($fp, fopen('php://output', 'w'));

        exit;
    }

    private function _getData() {
        $this->load->language('catalog/kiboimex');

        $data = array();

        $data['heading_title'] = 'Import/Export';

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->link('common/dashboard'),
        );

        $data['breadcrumbs'][] = array(
            'text' => $data['heading_title'],
            'href' => $this->link('catalog/kiboimex'),
        );

        $data['button_add'] = $this->language->get('button_add');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_edit'] = $this->language->get('button_edit');
        $data['button_delete'] = $this->language->get('button_delete');

        $data['text_confirm'] = $this->language->get('text_confirm');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        return $data;
    }

    private function _formatNumber($v) {
        if(!is_numeric($v)) {
            return $v;
        }
        $v = preg_replace('/(\.[0-9]+)0+$/', '$1', $v);
        $v = preg_replace('/\.0*$/', '', $v);
        return $v;
    }

    private function _parseNumber($v) {
        $v = trim($v);
        if(!preg_match('/^' . $this->re_number . '$/', $v)) {
            return false;
        }
        $v = str_replace(',', '.', $v);
        $v = ltrim($v, '0');
        return $v;
    }

    private function link($controller)
    {
        if(isset($this->session->data['user_token'])) {
            $param = 'user_token=' . $this->session->data['user_token'];
        } else {
            $param = 'token=' . $this->session->data['token'];
        }

        return $this->url->link($controller, $param, true);
    }
}
