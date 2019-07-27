<?php

namespace KiboImex;

use Controller;
use RecursiveDirectoryIterator;
use RuntimeException;
use SplFileInfo;

class FieldLoader {

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
            /** @var Field $field */
            try {
                $field = new $class($controller);
            } catch (UnavailableFieldException $e) {
                continue;
            }
            if (!$field instanceof Field) {
                throw new RuntimeException("Field class $class must implement Field interface");
            }
        }
        return $fields;
    }

}