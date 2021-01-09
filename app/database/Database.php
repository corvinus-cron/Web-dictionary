<?php

namespace classes\database;

use PDO;
use PDOException;

class Database
{
    use Singleton;
    public $db = null;

    private function __construct()
    {
        $dsn = 'mysql:dbname=dictionary;host=mariadb;port=3306;charset=utf8';
        try {
            $this->db = new PDO($dsn, 'root','docker');
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }

    public function begin()
    {
        $this->db->beginTransaction();
    }

    public function errorInfo()
    {
        return $this->db->errorInfo();
    }

    public function rollback()
    {
        $this->db->rollBack();
    }

    public function commit()
    {
        $this->db->commit();
    }

    public function setQueryData($map, $data)
    {
        $q = '(';
        foreach ($map as $name => $def) {
            if (isset($data[$name]) && !empty($data[$name])) {
                if (is_array($data[$name])) {
                    $data[$name] = json_encode(
                        $data[$name],
                        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
                    );
                }
                $q .= $this->db->quote($data[$name]) . ',';
            } else {
                $q .= $def . ',';
            }
        }
        return substr($q, 0, -1) . ')';
    }

    public function updateQueryData($map, $data)
    {
        $q = '';
        foreach ($map as $name => $def) {
            if ($name == 'id') continue;
            if (isset($data[$name])) {
                if (is_array($data[$name])) {
                    $data[$name] = json_encode(
                        $data[$name],
                        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
                    );
                }
                $q .= $name . '=' . $this->db->quote($data[$name]) . ',';
            } else {
//                $q .= $name . '=' . $this->db->quote($def) . ',';
            }
        }
        return substr($q, 0, -1);
    }

    public function escape($str = '')
    {
        return $this->db->quote($str);
    }

    public function reinitDb()
    {
        if (!empty($this->query('SHOW TABLES LIKE \'%dictionary%\';'))) {
            return true;
        }
        try {
//            $this->db->query('drop table dictionary');
            $this->db->exec("create table dictionary
(
    word text     default '',
    description   text     default '',
    date_create   datetime default now()
);");
            if ($this->db->errorInfo()[0] != '00000') {
                var_dump($this->db->errorInfo());
            }
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    public function query($q)
    {
        $res = $this->db->query($q);
        if ($res === false) {
            var_dump($this->errorInfo());
            throw new \Exception('ошибка запроса к бд');
        }
        $res = $res->fetchAll();
        return $res;
    }

    ///запрос на столбцы таблицы
    public function getMapDef($table)
    {
        $map = [];
        $res = $this->query("SHOW COLUMNS FROM {$table}");
        foreach ($res as $oneColumn) {
            $map[$oneColumn['Field']] = $oneColumn['Default'];
        }
        return $map;
    }

    public function getMapAs($table)
    {
        $map = [];
        $res = $this->query("SHOW COLUMNS FROM {$table}");
        foreach ($res as $oneColumn) {
            $map[$oneColumn['Field']] = $oneColumn['Field'];
        }
        return $map;
    }

    public function selectValue($select = [], $filter = [], $map = [], $hasTest = [], $table = '', $join = false)
    {
        if ($table != '') {
            $table = str_replace('.', '', $table) . '.';
        }
        $result = [];
        $has = [];
        $hasProp = [];
        foreach ($hasTest as $k => $v) {
            $has[$v] = true;
        }
        if (!empty($select)) {
            foreach ($hasTest as $k => $v) {
                $has[$v] = false;
            }
            if (!in_array('id', $select)) {
                if ($join) {
                    $select[] = array_search('id', $map);
                } else {
                    $select[] = 'id';
                }
            }
            foreach ($map as $k => $v) {
                if (in_array($k, $select)) {
                    $result[] = $table . $v . ' as ' . $k;
                }
            }
            foreach ($hasTest as $k => $v) {
                $has[$v] = in_array($v, $select) || isset($select[$v]);
                if (isset($select[$v])) {
                    $hasProp[$v] = $select[$v];
                }
            }
        }
        if (empty($result)) {
            foreach ($map as $k => $v) {
                $result[] = $table . $v . ' as ' . $k;
            }
        }
        $result = empty($result) ? '*' : implode(',', $result);
        $where = $this->createFilter($filter, $map, $table);
        $where = trim($where) == '' ? '' : (' WHERE ' . $where);

        return [
            'select' => $result,
            'where' => $where,
            'has' => $has,
            'hasProp' => $hasProp,
            'join' => ''
        ];
    }

    public function createFilter($filter = [], $map = [], $table = '')
    {
        if ($table != '') {
            $table = str_replace('.', '', $table) . '.';
        }
        $where = '';
        if (!empty($filter)) {
            $logic = 'AND';
            $w = [];
            foreach ($filter as $k => $v) {
                if ($k === 'logic') {
                    $logic = $v;
                } else {
                    if (is_numeric($k) && is_array($v)) {
                        $tmp = $this->createFilter($v, $map, $table);
                        if (!empty($tmp)) {
                            $w[] = $tmp;
                        }
                    } else {
                        $eq = [
                            substr($k, 0, 1),
                            substr($k, 1, 1)
                        ];
                        $k = preg_replace('/^[%!&$><=]+/', '', $k);
                        if (!empty($map[$k])) {
                            if ($eq[0] === "&" && is_array($v)) {
                                foreach ($v as $v_i => $v_v) {
                                    $v[$v_i] = $this->escape($v_v);
                                }
                                $w[] = $table . $map[$k] . " IN (" . implode(",", $v) . ")";
                            } elseif ($eq[0] === "$" && is_array($v)) {
                                foreach ($v as $v_i => $v_v) {
                                    $v[$v_i] = $this->escape($v_v);
                                }
                                $w[] = $table . $map[$k] . " NOT IN (" . implode(",", $v) . ")";
                            } elseif ($eq[0] === "%") {
                                $val = $this->escape($v);
                                $val = substr($val, 1, (strlen($val) - 2));
                                $w[] = $table . $map[$k] . " LIKE '%" . $val . "%'";
                            } elseif ($eq[0] === "!") {
                                $w[] = $table . $map[$k] . " != " . $this->escape($v);
                            } elseif ($eq[0] === "<") {
                                if ($eq[1] === "=") {
                                    $w[] = $table . $map[$k] . " <= " . $this->escape($v);
                                } else {
                                    $w[] = $table . $map[$k] . " < " . $this->escape($v);
                                }
                            } elseif ($eq[0] === ">") {
                                if ($eq[1] === "=") {
                                    //                                    $str = $this->escape($v);
                                    //                                    $str = "'>=".substr($str, 1, strlen($str));
                                    //                                    $w[] = $table.$map[$k] . $str;
                                    $w[] = $table . $map[$k] . " >= " . $this->escape($v);
                                } else {
                                    $w[] = $table . $map[$k] . " > " . $this->escape($v);
                                }
                            } else {
                                $w[] = $table . $map[$k] . " = " . $this->escape($v);
                            }
                        }
                    }
                }
            }
            $where = empty($w) ? '' : ' (' . implode(' ' . $logic . ' ', $w) . ') ';
        }
        return $where;
    }
}
