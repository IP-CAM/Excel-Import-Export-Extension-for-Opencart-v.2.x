<?php

namespace KiboImex;

use DB;

class Helpers {

    const RE_NUMBER = '[0-9]+(?:[,.][0-9]+)?';

    public static function formatNumber($v): string {
        $v = (string) $v;
        if(!is_numeric($v)) {
            return $v;
        }
        $v = preg_replace('/(\.[0-9]+)0+$/', '$1', $v);
        $v = preg_replace('/\.0*$/', '', $v);
        return $v;
    }

    public static function unescape($str): string {
        return html_entity_decode((string) $str, ENT_QUOTES, 'UTF-8');
    }

    public static function requireTable(DB $db, string $table) {
        if (!self::tableExists($db, $table)) {
            throw new UnavailableFieldException("Table missing: $table");
        }
    }

    public static function tableExists(DB $db, string $table): bool {
        $result = $db->query('SHOW TABLES');

        foreach ($result->rows as $row) {
            if (DB_PREFIX . $table == array_shift($row)) {
                return true;
            }
        }

        return false;
    }

    public static function requireColumn(DB $db, string $table, string $column) {
        if (!self::columnExists($db, $table, $column)) {
            throw new UnavailableFieldException("Column missing: $table.$column");
        }
    }

    public static function columnExists(DB $db, string $table, string $column): bool {
        $result = $db->query("DESCRIBE `" . DB_PREFIX . "$table`");

        foreach ($result->rows as $row) {
            if (!strcasecmp(array_shift($row), $column)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $v
     * @return false|string
     */
    public static function parseNumber($v) {
        $v = trim($v);
        if(!preg_match('/^' . self::RE_NUMBER . '$/', $v)) {
            return false;
        }
        $v = str_replace(',', '.', $v);
        $v = ltrim($v, '0');
        return $v;
    }

    public static function splitValues($str): array {
        $values = preg_split('/;/', $str);
        $values = array_unique(array_map('trim', $values));
        $values = array_filter($values, 'strlen');
        return $values;
    }

    public static function insert(DB $db, $table, array $row, array $opt = array()) {
        $verb = in_array('ignore', $opt) ? "INSERT IGNORE" : "INSERT";
        $sql = "{$verb} INTO `" . DB_PREFIX . "{$table}` SET " . self::makeSet($db, $row) . "";
        if(in_array('update', $opt))
            $sql .= " ON DUPLICATE KEY UPDATE " . self::makeSet($db, $row) . "";
        $db->query($sql);
        return $db->getLastId();
    }

    private static function makeSet(DB $db, array $row) {
        $set = array();
        foreach($row as $k => $v)
            $set[] = "`$k` = " . self::quote($db, $v) . "";
        return implode(", ", $set);
    }

    private static function quote(DB $db, $v) {
        if($v === null)
            return "NULL";
        else
            return "'" . $db->escape($v) . "'";
    }

    public static function update(DB $db, $table, array $where, array $row) {
        $db->query("
            UPDATE `" . DB_PREFIX . "{$table}`
            SET " . self::makeSet($db, $row) . "
            WHERE " . self::makeWhere($db, $where) . "
        ");
    }

    private static function makeWhere(DB $db, array $row) {
        $where = array();
        foreach($row as $k => $v)
            $where[] = "`$k` = " . self::quote($db, $v) . "";
        return implode(" AND ", $where);
    }

}