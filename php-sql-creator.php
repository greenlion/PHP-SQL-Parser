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
               $sql .=  " " .$this->processWHERE($parsed['WHERE']);
           }
           return $sql;
        }
        
        private function processUPDATE($parsed) {
            return "UPDATE ".$parsed[0]['table'];
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