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

        protected function processSelectStatement($parsed) {
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

        protected function processInsertStatement($parsed) {
            return $this->processINSERT($parsed['INSERT']) . " " . $this->processVALUES($parsed['VALUES']);
            # TODO: subquery?
        }

        protected function processDeleteStatement($parsed) {
            die("DELETE not implemented");
        }

        protected function processUpdateStatement($parsed) {
            $sql = $this->processUPDATE($parsed['UPDATE']) . " " . $this->processSET($parsed['SET']);
            if (isset($parsed['WHERE'])) {
                $sql .= " " . $this->processWHERE($parsed['WHERE']);
            }
            return $sql;
        }

        protected function processSELECT($parsed) {
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

        protected function processFROM($parsed) {
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

        protected function processORDER($parsed) {
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

        protected function processGROUP($parsed) {
            $sql = "GROUP BY ";
            // TODO: implement this
            return $sql;
        }

        protected function processVALUES($parsed) {
            $sql = "";
            foreach ($parsed as $k => $v) {
                $len = strlen($sql);
                $sql .= $this->processConstant($v);
                $sql .= $this->processFunction($v);
                $sql .= $this->processOperator($v);

                if ($len == strlen($sql)) {
                    die("unknown expr_type in VALUES[" . $k . "] " . $v['expr_type']);
                }

                $sql .= ",";
            }
            $sql = substr($sql, 0, strlen($sql) - 1);
            return "VALUES (" . $sql . ")";
        }

        protected function processINSERT($parsed) {
            $sql = "INSERT INTO " . $parsed['table'];

            if ($parsed['columns'] === false) {
                return $sql;
            }

            $columns = "";
            foreach ($parsed['columns'] as $k => $v) {
                $len = strlen($sql);
                $sql .= $this->processColRef($v);

                if ($len == strlen($sql)) {
                    die("unknown expr_type in INSERT[columns][" . $k . "] " . $v['expr_type']);
                }

                $sql .= ",";
            }

            if ($columns !== "") {
                $columns = " (" . substr($columns, 0, strlen($columns) - 1) . ")";
            }

            $sql .= $columns;
            return $sql;
        }

        protected function processUPDATE($parsed) {
            return "UPDATE " . $parsed[0]['table'];
        }

        protected function processSET($parsed) {
            $sql = "";
            foreach ($parsed as $k => $v) {
                $sql .= $v['column'] . "=" . $v['expr'] . ",";
            }
            $sql = substr($sql, 0, strlen($sql) - 1);
            return "SET " . $sql;
        }

        protected function processWHERE($parsed) {
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

        protected function processExpression($parsed) {
            if ($parsed['type'] !== 'expression') {
                return "";
            }
            return $parsed['base_expr'] . " " . $parsed['direction'];
        }

        protected function processFunction($parsed) {
            if (($parsed['expr_type'] !== 'aggregate_function') && ($parsed['expr_type'] !== 'function')) {
                return "";
            }

            if ($parsed['sub_tree'] === false) {
                return $parsed['base_expr']; // TODO: maybe we need ()!
            }

            $sql = "";
            foreach ($parsed['sub_tree'] as $k => $v) {
                $len = strlen($sql);
                $sql .= $this->processFunction($v);
                $sql .= $this->processConstant($v);

                if ($len == strlen($sql)) {
                    die("unknown expr_type in function subtree[" . $k . "] " . $v['expr_type']);
                }

                $sql .= ",";
            }
            return $parsed['base_expr'] . "(" . $sql . ")";
        }

        protected function processSelectExpression($parsed) {
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

        protected function processRefClause($parsed) {
            if ($parsed === false) {
                return "";
            }

            $sql = "";
            foreach ($parsed as $k => $v) {
                $len = strlen($sql);
                $sql .= $this->processColRef($v);
                $sql .= $this->processOperator($v);

                if ($len == strlen($sql)) {
                    die("unknown expr_type in expression ref_clause[" . $k . "] " . $v['expr_type']);
                }

                $sql .= " ";
            }
            return "(" . $sql . ")";
        }

        protected function processAlias($parsed) {
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

        protected function processJoin($parsed) {
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

        protected function processRefType($parsed) {
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

        protected function processTable($parsed, $index) {
            if ($parsed['expr_type'] !== 'table') {
                return "";
            }

            $sql = $parsed['table'];
            $sql .= " " . $this->processAlias($parsed['alias']);

            if ($index !== 0) {
                $sql = " " . $this->processJoin($parsed['join_type']) . " " . $sql;
                $sql .= $this->processRefType($parsed['ref_type']);
                $sql .= $this->processRefClause($parsed['ref_clause']);
            }
            return $sql;
        }

        protected function processTableExpression($parsed, $index) {
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

        protected function processOperator($parsed) {
            if ($parsed['expr_type'] !== 'operator') {
                return "";
            }
            return $parsed['base_expr'];
        }

        protected function processColRef($parsed) {
            if ($parsed['expr_type'] !== 'colref') {
                return "";
            }
            return $parsed['base_expr'];
        }

        protected function processConstant($parsed) {
            if ($parsed['expr_type'] !== 'const') {
                return "";
            }
            return $parsed['base_expr'];
        }

        protected function processInList($parsed) {
            if ($parsed['expr_type'] !== 'in-list') {
                return "";
            }
            $sql = "";
            foreach ($parsed['sub_tree'] as $k => $v) {
                $sql .= $v . ",";
            }
            return "(" . substr($sql, 0, strlen($sql) - 1) . ")";
        }

        protected function processSubquery($parsed) {
            if ($parsed['expr_type'] !== 'subquery') {
                return "";
            }
            return "(" . $this->create($parsed['sub_tree']) . ")";
        }

    } // END CLASS
    define('HAVE_PHP_SQL_CREATOR', 1);
}
