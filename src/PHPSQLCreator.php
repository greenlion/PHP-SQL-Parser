<?php
/**
 * PHPSQLCreator.php
 * 
 * A pure PHP SQL creator, which generates SQL from the output of PHPSQLParser.
 * 
 * Copyright (c) 2012, André Rothe <arothe@phosco.info, phosco@gmx.de>
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

require_once dirname(__FILE__) . '/exceptions/UnsupportedFeatureException.php';
require_once dirname(__FILE__) . '/utils/SelectStatementBuilder.php';
require_once dirname(__FILE__) . '/utils/DeleteStatementBuilder.php';
require_once dirname(__FILE__) . '/utils/UpdateStatementBuilder.php';
require_once dirname(__FILE__) . '/utils/InsertStatementBuilder.php';
require_once dirname(__FILE__) . '/utils/CreateStatementBuilder.php';

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
            throw new UnsupportedFeatureException($k);
            break;
        case "SELECT":
            $builder = new SelectStatementBuilder($parsed);
            $this->created = $builder->build($parsed);
            break;
        case "INSERT":
            $builder = new InsertStatementBuilder($parsed);
            $this->created = $builder->build($parsed);
            break;
        case "DELETE":
            $builder = new DeleteStatementBuilder($parsed);
            $this->created = $builder->build($parsed);
            break;
        case "UPDATE":
            $builder = new UpdateStatementBuilder($parsed);
            $this->created = $builder->build($parsed);
            break;
        case "RENAME":
            $this->created = $this->processRenameTableStatement($parsed);
            break;
        case "SHOW":
            $this->created = $this->processShowStatement($parsed);
            break;
        case "CREATE":
            $builder = new CreateStatementBuilder($parsed);
            $this->created = $builder->build($parsed);
            break;
        default:
            throw new UnsupportedFeatureException($k);
            break;
        }
        return $this->created;
    }

    protected function processShowStatement($parsed) {
        $sql = $this->processSHOW($parsed);
        if (isset($parsed['WHERE'])) {
            $sql .= " " . $this->processWHERE($parsed['WHERE']);
        }
        return $sql;
    }

    protected function processRenameTableStatement($parsed) {
        $rename = $parsed['RENAME'];
        $sql = "";
        foreach ($rename as $k => $v) {
            $len = strlen($sql);
            $sql .= $this->processSourceAndDestTable($v);

            if ($len == strlen($sql)) {
                throw new UnableToCreateSQLException('RENAME', $k, $v, 'expr_type');
            }

            $sql .= ",";
        }
        $sql = substr($sql, 0, -1);
        return "RENAME TABLE " . $sql;
    }

    protected function processSourceAndDestTable($v) {
        if (!isset($v['source']) || !isset($v['destination'])) {
            return "";
        }
        return $v['source']['base_expr'] . " TO " . $v['destination']['base_expr'];
    }

    protected function processLIMIT($parsed) {
        $sql = ($parsed['offset'] ? $parsed['offset'] . ", " : "") . $parsed['rowcount'];
        if ($sql === "") {
            throw new UnableToCreateSQLException('LIMIT', 'rowcount', $parsed, 'rowcount');
        }
        return "LIMIT " . $sql;
    }




    protected function processSET($parsed) {
        $sql = "";
        foreach ($parsed as $k => $v) {
            $len = strlen($sql);
            $sql .= $this->processSetExpression($v);

            if ($len == strlen($sql)) {
                throw new UnableToCreateSQLException('SET', $k, $v, 'expr_type');
            }

            $sql .= ",";
        }
        return "SET " . substr($sql, 0, -1);
    }




    protected function processLimitRowCount($key, $value) {
        if ($key != 'rowcount') {
            return "";
        }
        return $value;
    }

    protected function processLimitOffset($key, $value) {
        if ($key !== 'offset') {
            return "";
        }
        return $value;
    }

    protected function processFunction($parsed) {
        if (($parsed['expr_type'] !== ExpressionType::AGGREGATE_FUNCTION)
            && ($parsed['expr_type'] !== ExpressionType::SIMPLE_FUNCTION)) {
            return "";
        }

        if ($parsed['sub_tree'] === false) {
            return $parsed['base_expr'] . "()";
        }

        $sql = "";
        foreach ($parsed['sub_tree'] as $k => $v) {
            $len = strlen($sql);
            $sql .= $this->processFunction($v);
            $sql .= $this->processConstant($v);
            $sql .= $this->processColRef($v);
            $sql .= $this->processReserved($v);
            $sql .= $this->processSelectBracketExpression($v);
            $sql .= $this->processSelectExpression($v);

            if ($len == strlen($sql)) {
                throw new UnableToCreateSQLException('function subtree', $k, $v, 'expr_type');
            }

            $sql .= ($this->isReserved($v) ? " " : ",");
        }
        return $parsed['base_expr'] . "(" . substr($sql, 0, -1) . ")" . $this->processAlias($parsed);
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
            $sql .= $this->processConstant($v);

            if ($len == strlen($sql)) {
                throw new UnableToCreateSQLException('expression ref_clause', $k, $v, 'expr_type');
            }

            $sql .= " ";
        }
        return "(" . substr($sql, 0, -1) . ")";
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
        // TODO: add more
        throw new UnsupportedFeatureException($parsed);
    }

    protected function processTable($parsed, $index) {
        if ($parsed['expr_type'] !== ExpressionType::TABLE) {
            return "";
        }

        $sql = $parsed['table'];
        $sql .= $this->processAlias($parsed);

        if ($index !== 0) {
            $sql = $this->processJoin($parsed['join_type']) . $sql;
            $sql .= $this->processRefType($parsed['ref_type']);
            $sql .= $this->processRefClause($parsed['ref_clause']);
        }
        return $sql;
    }

    protected function processTableExpression($parsed, $index) {
        if ($parsed['expr_type'] !== ExpressionType::TABLE_EXPRESSION) {
            return "";
        }
        $sql = substr($this->processFROM($parsed['sub_tree']), 5); // remove FROM keyword
        $sql = "(" . $sql . ")";
        $sql .= $this->processAlias($parsed);

        if ($index !== 0) {
            $sql = $this->processJoin($parsed['join_type']) . $sql;
            $sql .= $this->processRefType($parsed['ref_type']);
            $sql .= $this->processRefClause($parsed['ref_clause']);
        }
        return $sql;
    }

    protected function processSubQuery($parsed, $index = 0) {
        if ($parsed['expr_type'] !== ExpressionType::SUBQUERY) {
            return "";
        }

        $sql = $this->processSelectStatement($parsed['sub_tree']);
        $sql = "(" . $sql . ")";
        $sql .= $this->processAlias($parsed);

        if ($index !== 0) {
            $sql = $this->processJoin($parsed['join_type']) . $sql;
            $sql .= $this->processRefType($parsed['ref_type']);
            $sql .= $this->processRefClause($parsed['ref_clause']);
        }
        return $sql;
    }

    protected function processInList($parsed) {
        if ($parsed['expr_type'] !== ExpressionType::IN_LIST) {
            return "";
        }
        $sql = $this->processSubTree($parsed, ",");
        return "(" . $sql . ")";
    }
}
?>
