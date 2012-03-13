<?php
error_reporting(E_ALL);

if (!defined('HAVE_PHP_SQL_CREATOR')) {

    class PHPSQLCreator {

        public function __construct($parsed = false) {
            if ($parsed) {
                $this->create($parsed);
            }
        }

        public function create($parsed) {
            $k = key($parsed);
            switch ($k) {

            case "UNION":
            case "UNION ALL":
                die("UNIONS");
                break;
            case "SELECT":
                $this->created = $this->processSelectStatement($parsed);
                break;
            case "INSERT":
                $this->created = $this->processInsertStatement($parsed);
                break;
            case "DELETE":
                $this->created = $this->processDeleteStatement($parsed);
                break;
            case "UPDATE":
                $this->created = $this->processUpdateStatement($parsed);
                break;
            default:
                die("unknown key " . $k);
                break;
            }
        }

        private function processSelectStatement($parsed) {
            $sql = $this->processSELECT($parsed['SELECT']) . " " . $this->processFROM($parsed['FROM']);
            if (isset($parsed['WHERE'])) {
                $sql .= " " . $this->processWHERE($parsed['WHERE']);
            }
            if (isset($parsed['ORDER'])) {
                $sql .= " " . $this->processORDER($parsed['ORDER']);
            }
            if (isset($parsed['GROUP'])) {
                $sql .= " " . $this->processGROUP($parsed['GROUP']);
            }
            return $sql;
        }

        private function processSELECT($parsed) {
            $values = "";
            foreach ($parsed as $k => $v) {
                if ($v['expr_type'] === 'colref') {
                    $values .= $v['base_expr'] . ",";
                } else {
                    die("unknown expr_type in SELECT[" . $k . "] " . $v['expr_type']);
                }
            }
            $values = substr($values, 0, strlen($values) - 1);
            return "SELECT " . $values;
        }

        private function processRefClause($parsed) {
            if ($parsed === false) {
                return "";
            }

            $sql = "";
            foreach ($parsed as $k => $v) {
                $sql .= $v['base_expr'] . " ";
            }
            return "(" . $sql . ")";
        }

        private function processAlias($parsed) {
            if ($parsed === false) {
                return "";
            }

            $sql = "";
            if ($parsed['as']) {
                $sql .= " as";
            }
            $sql .= " " . $parsed['name'];
            return $sql;
        }

        private function processJoin($parsed) {
            if ($parsed === 'CROSS') {
                return ",";
            }
            if ($parsed === 'JOIN') {
                return "INNER JOIN";
            }
            // TODO: add more
        }
        
        private function processTable($parsed, $key) {
            if ($parsed['expr_type'] !== 'table') {
                return "";
            }

            $sql = $parsed['table'];
            $sql .= $this->processAlias($parsed['alias']);

            if ($key !== 0) {
                $sql = $this->processJoin($parsed['join_type']) . " " . $sql;
                if ($parsed['ref_type'] === 'ON') {
                    $sql .= " ON ";
                }
                $sql .= $this->processRefClause($parsed['ref_clause']);
            }
            return $sql;
        }

        private function processFROM($parsed) {
            $values = "";
            foreach ($parsed as $k => $v) {
                $values .= $this->processTable($v, $k) . " ";

                if ($v['expr_type'] !== 'table') {
                    die("unknown expr_type in FROM[" . $k . "] " . $v['expr_type']);
                }
            }
            return "FROM " . $values;
        }

        private function processORDER($parsed) {
            $values = "";
            foreach ($parsed as $k => $v) {
                if ($v['type'] === 'expression') {
                    $values .= $v['base_expr'] . " " . $v['direction'] . ",";
                } else {
                    die("unknown type in ORDER[" . $k . "] " . $v['type']);
                }
            }
            $values = substr($values, 0, strlen($values) - 1);
            return "ORDER BY " . $values;
        }

        private function processGROUP($parsed) {
            $sql = "GROUP BY ";

            return $sql;
        }

        private function processInsertStatement($parsed) {
            return $this->processINSERT($parsed['INSERT']) . " " . $this->processVALUES($parsed['VALUES']);
            # TODO: subquery?
        }

        private function processVALUES($parsed) {
            $values = "";
            foreach ($parsed as $k => $v) {
                $values .= $v['base_expr'] . ",";
            }
            $values = substr($values, 0, strlen($values) - 1);
            $sql = "VALUES (" . $values . ")";
            return $sql;
        }

        private function processINSERT($parsed) {
            $sql = "INSERT INTO " . $parsed['table'];

            $columns = "";
            foreach ($parsed['columns'] as $k => $v) {
                $columns .= $v['base_expr'] . ",";
            }
            if ($columns !== "") {
                $columns = " (" . substr($columns, 0, strlen($columns) - 1) . ")";
            }
            $sql .= $columns;
            return $sql;
        }

        private function processDeleteStatement($parsed) {

        }

        private function processUpdateStatement($parsed) {
            $sql = $this->processUPDATE($parsed['UPDATE']) . " " . $this->processSET($parsed['SET']);
            if (isset($parsed['WHERE'])) {
                $sql .= " " . $this->processWHERE($parsed['WHERE']);
            }
            return $sql;
        }

        private function processUPDATE($parsed) {
            return "UPDATE " . $parsed[0]['table'];
        }

        private function processSET($parsed) {
            $values = "";
            foreach ($parsed as $k => $v) {
                $values .= $v['column'] . "=" . $v['expr'] . ",";
            }
            $values = substr($values, 0, strlen($values) - 1);
            $sql = "SET " . $values;
            return $sql;
        }

        private function processWHERE($parsed) {
            $sql = "WHERE ";
            foreach ($parsed as $k => $v) {
                $sql .= $v['base_expr'];
            }
            # subquery, expressions?
            return $sql;
        }

    } // END CLASS
    define('HAVE_PHP_SQL_CREATOR', 1);
}
