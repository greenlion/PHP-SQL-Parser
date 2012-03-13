<?php
/**
 * OracleSQLTranslator
 * 
 * A translator from MySQL dialect into Oracle dialect for Limesurvey 
 * (http://www.limesurvey.org/)
 * 
 * Copyright (c) 2012, André Rothe <arothe@phosco.info>
 * 
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, 
 * are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice, 
 *     this list of conditions and the following disclaimer.
 *   * Redistributions in binary form must reproduce the above copyright notice, 
 *     this list of conditions and the following disclaimer in the documentation 
 *     and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY 
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES 
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT 
 * SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, 
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED 
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR 
 * BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN 
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN 
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH 
 * DAMAGE.
 */
require_once('php-sql-parser.php');
require_once('php-sql-creator.php');
require_once($rootdir . '/classes/adodb/adodb.inc.php');

class OracleSQLTranslator extends PHPSQLCreator {

    private $con; # this is the database connection from LimeSurvey
    private $preventColumnRefs = false;
    private $allTables = array();

    public function __construct($con) {
        parent::__construct();
        $this->con = $con;
        $this->initGlobalVariables();
    }

    private function preprint($s, $return = false) {
        $x = "<pre>";
        $x .= print_r($s, 1);
        $x .= "</pre>";
        if ($return)
            return $x;
        else if (isset($_ENV['DEBUG'])) {
            print $x;
        }
    }

    protected function processAlias($parsed) {
        if ($parsed === false) {
            return "";
        }
        # we don't need an AS between expression and alias
        $sql = " " . $parsed['name'];
        return $sql;
    }

    protected function processDELETE($parsed) {
        if (count($parsed('TABLES')) > 1) {
            die("cannot translate delete statement into Oracle dialect, multiple tables are not allowed.");
        }
        return "DELETE";
    }

    private function getColumnNameFor($column) {
        if (strtolower($column) === 'uid') {
            $column = "uid_";
        }
        return $column;
    }

    private function getShortTableNameFor($table) {
        if (strtolower($table) === 'surveys_languagesettings') {
            $table = 'surveys_lngsettings';
        }
        return $table;
    }

    protected function processTable($parsed, $index) {
        global $allTables;

        if ($parsed['expr_type'] !== 'table') {
            return "";
        }

        $sql = $table = $this->getShortTableNameFor($parsed['table']);
        $sql .= " " . $this->processAlias($parsed['alias']);

        if ($index !== 0) {
            $sql = " " . $this->processJoin($parsed['join_type']) . " " . $sql;
            $sql .= $this->processRefType($parsed['ref_type']);
            $sql .= $this->processRefClause($parsed['ref_clause']);
        }

        if ($sql !== "") {
            // TODO: the alias can be wrong, if the table is part of a table-expression with its own alias
            $allTables[] = array('table' => $parsed['table'], 'alias' => $this->processAlias($parsed['alias']));
        }
        return $sql;
    }

    private function getTableNameFromExpression($expr) {
        $pos = strpos($expr, ".");
        if ($pos === false) {
            $pos = -1;
        }
        return trim(substr($expr, 0, $pos + 1), ".");
    }

    private function getColumnNameFromExpression($expr) {
        $pos = strpos($expr, ".");
        if ($pos === false) {
            $pos = -1;
        }
        return substr($expr, $pos + 1);
    }

    private function isCLOBColumn($table, $column) {
        global $allTables;

        if ($table === "") {
            foreach ($allTables as $k => $v) {
                echo "check for table " . $v['table'];
                if ($this->isCLOBColumn($v['table'], $column)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($allTables as $k => $v) {
            if (strtolower($v['alias']) === strtolower($table)) {
                echo "check for table " . $v['table'] . " because of alias " . $v['alias'];
                if ($this->isCLOBColumn($v['table'], $column)) {
                    return true;
                }
            }
        }

        $res = $this->con->GetOne(
                "SELECT count(*) FROM user_lobs WHERE table_name='" . strtoupper($table) . "' AND column_name='"
                        . strtoupper($column) . "'");
        return ($res >= 1);
    }

    protected function processExpression($parsed) {
        if ($parsed['type'] !== 'expression') {
            return "";
        }

        $table = $this->getTableNameFromExpression($parsed['base_expr']);
        $col = $this->getColumnNameFromExpression($parsed['base_expr']);

        $sql = ($table !== "" ? $table . "." : "") . $col;

        # check, if the column is a CLOB
        if ($this->isCLOBColumn($table, $col)) {
            $sql = "cast(substr(" . $sql . ",1,200) as varchar2(200))";
        }

        return $sql . " " . $parsed['direction'];
    }

    protected function processColRef($parsed) {
        global $preventColumnRefs;

        if ($parsed['expr_type'] !== 'colref') {
            return "";
        }

        $table = $this->getTableNameFromExpression($parsed['base_expr']);
        $col = $this->getColumnNameFromexpression($parsed['base_expr']);

        # we have to change the column name, if the column is uid
        $col = $this->getColumnNameFor($col);

        # we have to change the tablereference, if the tablename is too long
        $table = $this->getShortTableNameFor($table);

        # if we have * as colref, we cannot use other columns
        $preventColumnRefs = $preventColumnRefs || (($table === "") && ($col === "*"));

        $alias = "";
        if (isset($parsed['alias'])) {
            $alias = $this->processAlias($parsed['alias']);
        }

        return (($table !== "") ? ($table . "." . $col) : $col) . $alias;
    }

    protected function processSELECT($parsed) {
        global $preventColumnRefs;

        $sql = parent::processSELECT($parsed);
        if ($preventColumnRefs) {
            $sql = "SELECT *";
            $preventColumnRefs = false;
        }
        return $sql;
    }

    private function initGlobalVariables() {
        global $preventColumnRefs;
        global $allTables;

        $preventColumnRefs = false;
        $allTables = array();
    }

    public function process($sql) {
        $this->initGlobalVariables();

        $parser = new PHPSQLParser($sql);
        $this->preprint($parser->parsed);
        return $this->create($parser->parsed);
    }
}
