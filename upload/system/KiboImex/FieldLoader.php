<?php

namespace KiboImex;

use Controller;
use RecursiveDirectoryIterator;
use RuntimeException;

class FieldLoader {
    private $disableFields;

    public function __construct() {
        $configFile = DIR_SYSTEM . 'config/kiboimex.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        } else {
            $config = [];
        }
        $this->disableFields = $config['disableFields'] ?? [];
    }

    /** @phan-suppress-next-line PhanUnreferencedPublicMethod */
    public function getFields(Controller $controller): array {
        $fields = [];
        $dir = __DIR__ . '/Fields';
        $it = new RecursiveDirectoryIterator($dir);
        /** @var SplFileInfo $info */
        foreach ($it as $name => $info) {
            if (!$info->isFile() || $info->getExtension() != 'php') {
                continue;
            }
            $relativePath = substr($name, strlen(__DIR__));
            $class = __NAMESPACE__ . str_replace('/', '\\', substr($relativePath, 0, strlen($relativePath) - 4));
            if ($this->isDisabled($class)) {
                continue;
            }
            /** @var Field $field */
            try {
                $field = new $class($controller);
            } catch (UnavailableFieldException $e) {
                continue;
            }
            if (!$field instanceof Field) {
                throw new RuntimeException("Field class $class must implement Field interface");
            }
            $fields[] = $field;
        }
        return $fields;
    }

    private function isDisabled(string $class): bool {
        foreach ($this->disableFields as $disabledClass) {
            if (is_a($class, $disabledClass)) {
                return true;
            }
        }
        return false;
    }
}