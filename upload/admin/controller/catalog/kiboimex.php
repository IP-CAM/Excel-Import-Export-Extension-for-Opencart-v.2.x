<?php
use KiboImex\ExportRow;
use KiboImex\ExportRowCollection;
use KiboImex\Field;
use KiboImex\FieldLoader;
use KiboImex\Helpers;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once DIR_SYSTEM . 'KiboImex/autoload.php';
require_once DIR_SYSTEM . 'library/phpexcel/Classes/PHPExcel.php';
require_once DIR_SYSTEM . 'helper/kiboimex.php';

if (file_exists(DIR_SYSTEM . 'library/unic/unic.php')) {
    require_once DIR_SYSTEM . 'library/unic/unic.php';
}

class ControllerCatalogKiboimex extends Controller {
    private $data;
    private $currentRow;

    /** @var Field[] */
    private $fields;

    public function index() {
        $this->load->model('catalog/product');

        $this->initializeDatabase();

        $this->document->setTitle('Import/Export');

        $this->data = $this->getData();

        $this->fields = (new FieldLoader())->getFields($this);

        if(!empty($_FILES['import']))
            $this->import();
        elseif(!empty($_POST['export']))
            $this->export();

        $result = $this->db->query("SELECT COUNT(*) n FROM `" . DB_PREFIX . "product` WHERE kiboimex_imported = 1");
        $this->data['imported_count'] = $result->row['n'];

        $this->response->setOutput($this->renderView('catalog/kiboimex.tpl', $this->data));
    }

    private function initializeDatabase() {
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

    private function getData() {
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

    private function import() {
        $upload = $_FILES['import'];

        // Handle file upload
        if(!isset($upload['error']) || is_array($upload['error']))
            return $this->addError("Er zijn meerdere bestanden geüpload. Probeer het opnieuw.");

        switch($upload['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return $this->addError("Er is geen bestand geüpload. Probeer het opnieuw.");
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return $this->addError("Het bestand is te groot.");
            default:
                return $this->addError("Er is een onbekende fout opgetreden. Foutcode: {$upload['error']}.");
        }

        // Read Excel file.
        try {
            $excel = PHPExcel_IOFactory::load($upload['tmp_name']);
            $rows = ExportRowCollection::fromSheet($excel->getActiveSheet());
        } catch(Exception $e) {
            return $this->addError($e->getMessage());
        }

        // Check header.
        if (!$rows->getHeader()->hasField('ID')) {
            return $this->addError("Het veld ID ontbreekt.");
        }

        // Import products.
        $this->currentRow = 1;

        $this->data['inserted'] = 0;
        $this->data['updated'] = 0;
        $this->data['deleted'] = 0;

        $this->db->query("BEGIN");

        if (!empty($_POST['delete_imported'])) {
            $result = $this->db->query("
                SELECT product_id
                FROM `" . DB_PREFIX . "product`
                WHERE
                    kiboimex_imported = 1
            ");

            foreach ($result->rows as $row) {
                $this->model_catalog_product->deleteProduct($row['product_id']);
                $this->data['deleted']++;
            }
        }

        foreach ($rows as $row) {
            $this->currentRow++;

            if ($row['ID'] == '') {
                $product_id = null;
            } elseif(preg_match('/^[1-9][0-9]*$/', $row['ID'])) {
                $result = $this->db->query("
                    SELECT product_id
                    FROM `" . DB_PREFIX . "product`
                    WHERE
                        product_id = " . (int) $row['ID'] . "
                ");
                $product_id = $result->row['product_id'] ?? null;
            } else {
                $product_id = null;
                $this->addWarning("Ongeldige invoer in veld: ID");
            }

            if ($product_id) {
                $this->data['updated']++;
                Helpers::update($this->db, 'product', ['product_id' => $product_id], [
                    'kiboimex_imported' => 1,
                ]);
            } else {
                $product_id = Helpers::insert($this->db, 'product', [
                    'kiboimex_imported' => 1,
                ]);
                $this->data['inserted']++;
            }

            foreach ($this->fields as $field) {
                $field->import($product_id, $row);
            }
        }

        $this->db->query("COMMIT");

        $this->cache->delete('product');

        try {
            $this->load->model('module/brainyfilter');
        } catch (Throwable $e) {}
        if (class_exists(ModelModuleBrainyFilter::class)) {
            (new ModelModuleBrainyFilter($this->registry))->cacheProductProperties();
        }

        $this->data['success'] = true;
    }

    private function export() {
        $result = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` ORDER BY product_id");

        $exportRows = new ExportRowCollection();

        foreach($result->rows as $row) {
            $product_id = $row['product_id'];

            // Get product info.
            $product = $this->model_catalog_product->getProduct($product_id);

            if (!$product) {
                continue;
            }

            $exportRow = new ExportRow();
            foreach ($this->fields as $field) {
                $exportRow = $exportRow->merge($field->export($product));
            }

            $exportRows = $exportRows->addRow($exportRow);
        }

        // Generate Excel sheet.
        $excel = new PHPExcel;
        $sheet = $excel->setActiveSheetIndex(0);
        $exportRows->writeSheet($sheet);
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

    private function renderView($__template, array $__data) {
        extract($__data);
        ob_start();
        include DIR_TEMPLATE . $__template;
        return ob_get_clean();
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

    private function addError($message) {
        $this->data['errors'][] = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }

    public function addWarning($message) {
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $this->data['warnings'][$message][] = $this->currentRow;
    }
}
