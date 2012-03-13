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
            return $this->created;
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

        private function processInsertStatement($parsed) {
            return $this->processINSERT($parsed['INSERT']) . " " . $this->processVALUES($parsed['VALUES']);
            # TODO: subquery?
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

        private function processSELECT($parsed) {
            $sql = "";
            foreach ($parsed as $k => $v) {
                $len = strlen($sql);
                $sql .= $this->processColRef($v);
                $sql .= $this->processSelectExpression($v);

                if ($len == strlen($sql)) {
                    die("unknown expr_type in SELECT[" . $k . "] " . $v['expr_type']);
                }

                $sql .= ",";
            }
            $sql = substr($sql, 0, strlen($sql) - 1);
            return "SELECT " . $sql;
        }

        private function processFROM($parsed) {
            $sql = "";
            foreach ($parsed as $k => $v) {
                $len = strlen($sql);
                $sql .= $this->processTable($v, $k);
                $sql .= $this->processTableExpression($v, $k);

                if ($len == strlen($sql)) {
                    die("unknown expr_type in FROM[" . $k . "] " . $v['expr_type']);
                }

                $sql .= " ";
            }
            return "FROM " . $sql;
        }

        private function processORDER($parsed) {
            $sql = "";
            foreach ($parsed as $k => $v) {
                $len = strlen($sql);
                $sql .= $this->processExpression($v);

                if ($len == strlen($sql)) {
                    die("unknown type in ORDER[" . $k . "] " . $v['type']);
                }

                $sql .= ",";
            }
            $sql = substr($sql, 0, strlen($sql) - 1);
            return "ORDER BY " . $sql;
        }

        private function processGROUP($parsed) {
            $sql = "GROUP BY ";

            return $sql;
        }

        private function processVALUES($parsed) {
            $sql = "";
            foreach ($parsed as $k => $v) {
                $sql .= $v['base_expr'] . ",";
            }
            $sql = substr($sql, 0, strlen($sql) - 1);
            return "VALUES (" . $sql . ")";
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

        private function processUPDATE($parsed) {
            return "UPDATE " . $parsed[0]['table'];
        }

        private function processSET($parsed) {
            $sql = "";
            foreach ($sql as $k => $v) {
                $sql .= $v['column'] . "=" . $v['expr'] . ",";
            }
            $sql = substr($sql, 0, strlen($sql) - 1);
            return "SET " . $sql;
        }

        private function processWHERE($parsed) {
            $sql = "WHERE ";
            foreach ($parsed as $k => $v) {
                $len = strlen($sql);

                $sql .= $this->processOperator($v);
                $sql .= $this->processConstant($v);
                $sql .= $this->processColRef($v);
                $sql .= $this->processSubquery($v);
                $sql .= $this->processInList($v);

                if (strlen($sql) == $len) {
                    die("unknown expr_type in FROM[" . $k . "] " . $v['expr_type']);
                }

                $sql .= " ";
            }
            # expressions, functions?
            return $sql;
        }

        private function processExpression($parsed) {
            if ($parsed['type'] !== 'expression') {
                return "";
            }
            return $parsed['base_expr'] . " " . $parsed['direction'];
        }

        private function processFunction($parsed) {
            if ($parsed['expr_type'] !== 'aggregate_function') {
                return "";
            }
            return $parsed['base_expr'];
            // TODO: should we remove the parenthesis from the argument
        }

        private function processSelectExpression($parsed) {
            if ($parsed['expr_type'] !== 'expression') {
                return "";
            }
            $sql = "";
            foreach ($parsed['sub_tree'] as $k => $v) {
                $len = strlen($sql);
                $sql .= $this->processFunction($v);
                $sql .= $this->processConstant($v);

                if ($len == strlen($sql)) {
                    die("unknown expr_type in expression subtree[" . $k . "] " . $v['expr_type']);
                }

                $sql .= " ";
            }

            $sql .= $this->processAlias($parsed['alias']);
            return $sql;
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
            if ($parsed === 'LEFT') {
                return "LEFT JOIN";
            }
            // TODO: add more
            die("unknown join type " . $parsed);
        }

        private function processRefType($parsed) {
            if ($parsed === false) {
                return "";
            }
            if ($parsed === 'ON') {
                return " ON ";
            }
            if ($parsed === 'USING') {
                return " USING ";
            }
            die("unknown ref type " . $parsed);
        }

        private function processTable($parsed, $index) {
            if ($parsed['expr_type'] !== 'table') {
                return "";
            }

            $sql = $parsed['table'];
            $sql .= $this->processAlias($parsed['alias']);

            if ($index !== 0) {
                $sql = $this->processJoin($parsed['join_type']) . " " . $sql;
                $sql .= $this->processRefType($parsed['ref_type']);
                $sql .= $this->processRefClause($parsed['ref_clause']);
            }
            return $sql;
        }

        private function processTableExpression($parsed, $index) {
            if ($parsed['expr_type'] !== 'table_expression') {
                return "";
            }
            $sql = substr($this->processFROM($parsed['sub_tree']), 5); // remove FROM keyword
			$sql = "(" . $sql . ")";
            $sql .= $this->processAlias($parsed['alias']);
			
            if ($index !== 0) {
                $sql = $this->processJoin($parsed['join_type']) . " " . $sql;
                $sql .= $this->processRefType($parsed['ref_type']);
                $sql .= $this->processRefClause($parsed['ref_clause']);
            }
            return $sql;
        }

        private function processOperator($parsed) {
            if ($parsed['expr_type'] !== 'operator') {
                return "";
            }
            return $parsed['base_expr'];
        }

        private function processColRef($parsed) {
            if ($parsed['expr_type'] !== 'colref') {
                return "";
            }
            return $parsed['base_expr'];
        }

        private function processConstant($parsed) {
            if ($parsed['expr_type'] !== 'const') {
                return "";
            }
            return $parsed['base_expr'];
        }

        private function processInList($parsed) {
            if ($parsed['expr_type'] !== 'in-list') {
                return "";
            }
            $sql = "";
            foreach ($parsed['sub_tree'] as $k => $v) {
                $sql .= $v . ",";
            }
            return "(" . substr($sql, 0, strlen($sql) - 1) . ")";
        }

        private function processSubquery($parsed) {
            if ($parsed['expr_type'] !== 'subquery') {
                return "";
            }
            return "(" . $this->create($parsed['sub_tree']) . ")";
        }

    } // END CLASS
    define('HAVE_PHP_SQL_CREATOR', 1);
}
