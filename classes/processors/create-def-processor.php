<?php
/**
 * col-def-processor.php
 *
 * This file implements the processor for the column definitions within the TABLE statements.
 *
 * Copyright (c) 2010-2012, Justin Swanhart
 * with contributions by André Rothe <arothe@phosco.info, phosco@gmx.de>
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
if (!defined('HAVE_CREATE_DEF_PROCESSOR')) {
    require_once(dirname(__FILE__) . '/abstract-processor.php');
    require_once(dirname(__FILE__) . '/column-list-processor.php');
    require_once(dirname(__FILE__) . '/../expression-types.php');

    /**
     * 
     * This class processes the column definitions of the TABLE statements.
     * 
     * @author arothe
     * 
     */
    class CreateDefProcessor extends AbstractProcessor {

        protected function isIndexType($upper) {
            return ($upper === 'BTREE' || $upper === 'HASH');
        }

        protected function correctExpressionType(&$expr) {
            $type = ExpressionType::EXPRESSION;
            if (!isset($expr[0]) || !isset($expr[0]['type'])) {
                return $type;
            }
            $type = $expr[0]['type'];
            $expr[0]['type'] = ExpressionType::RESERVED;

            # replace the constraint type with a more descriptive one
            if ($type === ExpressionType::CONSTRAINT) {
                if ($expr[1]['type'] === ExpressionType::CONSTANT) {
                    $type = $expr[2]['type'];
                    $expr[2]['type'] = ExpressionType::RESERVED;
                } else {
                    $type = $expr[1]['type'];
                    $expr[1]['type'] = ExpressionType::RESERVED;
                }
            }
            return $type;
        }

        public function process($tokens) {

            $base_expr = "";
            $prevCategory = "";
            $currCategory = "";
            $expr = array();
            $result = array();

            foreach ($tokens as $k => $token) {

                $trim = trim($token);
                $base_expr .= $token;

                if ($trim === "") {
                    continue;
                }

                $upper = strtoupper($trim);

                switch ($upper) {

                case 'CONSTRAINT':
                    $expr[] = array('type' => ExpressionType::CONSTRAINT, 'base_expr' => $trim);
                    $currCategory = $prevCategory = $upper;
                    continue 2;

                case 'LIKE':
                    $expr[] = array('type' => ExpressionType::LIKE, 'base_expr' => $trim);
                    $currCategory = $prevCategory = $upper;
                    continue 2;

                case 'FOREIGN':
                    if ($prevCategory === "" || $prevCategory === "CONSTRAINT") {
                        $expr[] = array('type' => ExpressionType::FOREIGN_KEY, 'base_expr' => $trim);
                        if ($prevCategory === "CONSTRAINT") {
                            $expr[0]['for'] = ExpressionType::FOREIGN_KEY;
                        }
                        $currCategory = $upper;
                        continue 2;
                    }
                    # else ?
                    break;

                case 'PRIMARY':
                    if ($prevCategory === "" || $prevCategory === "CONSTRAINT") {
                        # next one is KEY
                        $expr[] = array('type' => ExpressionType::PRIMARY_KEY, 'base_expr' => $trim);
                        if ($prevCategory === "CONSTRAINT") {
                            $expr[0]['for'] = ExpressionType::PRIMARY_KEY;
                        }
                        $currCategory = $upper;
                        continue 2;
                    }
                    # else ?
                    break;

                case 'UNIQUE':
                    if ($prevCategory === "" || $prevCategory === "CONSTRAINT") {
                        # next one is KEY
                        $expr[] = array('type' => ExpressionType::UNIQUE_IDX, 'base_expr' => $trim);
                        if ($prevCategory === "CONSTRAINT") {
                            $expr[0]['for'] = ExpressionType::UNIQUE_IDX;
                        }
                        $currCategory = $upper;
                        continue 2;
                    }
                    # else ?
                    break;

                case 'KEY':
                # the next one is an index name
                    if ($currCategory === 'PRIMARY' || $currCategory === 'FOREIGN' || $currCategory === 'UNIQUE') {
                        $expr[] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        continue 2;
                    }
                    $expr[] = array('type' => ExpressionType::INDEX, 'base_expr' => $trim);
                    $currCategory = $upper;
                    continue 2;

                case 'CHECK':
                    $expr[] = array('type' => ExpressionType::CHECK, 'base_expr' => $trim);
                    $currCategory = $upper;
                    continue 2;

                case 'INDEX':
                    if ($currCategory === 'UNIQUE' || $currCategory === 'FULLTEXT' || $currCategory === 'SPATIAL') {
                        $expr[] = array('type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                        continue 2;
                    }
                    $expr[] = array('type' => ExpressionType::INDEX, 'base_expr' => $trim);
                    $currCategory = $upper;
                    continue 2;

                case 'FULLTEXT':
                    $expr[] = array('type' => ExpressionType::FULLTEXT_IDX, 'base_expr' => $trim);
                    $currCategory = $prevCategory = $upper;
                    continue 2;

                case 'SPATIAL':
                    $expr[] = array('type' => ExpressionType::SPATIAL_IDX, 'base_expr' => $trim);
                    $currCategory = $prevCategory = $upper;
                    continue 2;

                case 'WITH':
                    break;

                case 'PARSER':
                    break;

                case ',':
                # this starts the next definition
                    $type = $this->correctExpressionType($expr);
                    $result['create-def'][] = array('type' => $type,
                                                    'base_expr' => trim(substr($base_expr, 0, strlen($base_expr) - 1)),
                                                    'sub_tree' => $expr);
                    $base_expr = "";
                    $expr = array();
                    break;

                default:
                    switch ($currCategory) {

                    case 'LIKE':
                    # this is the tablename after LIKE
                        $expr[] = array('type' => ExpressionType::TABLE, 'table' => $trim, 'base_expr' => $trim,
                                        'no_quotes' => $this->revokeQuotation($trim));
                        break;

                    case 'PRIMARY':
                    # TODO: should we change the category?
                        if ($upper[0] === '(' && substr($upper, -1) === ')') {
                            $processor = new ColumnListProcessor();
                            $expr[] = $processor->process($this->removeParenthesisFromStart($trim));
                            $currCategory = "PRIMARY-COLUMNS";
                            continue 3;
                        }
                        if ($this->isIndexType($upper)) {
                            $expr[] = array('type' => ExpressionType::INDEX_TYPE, 'base_expr' => $trim);
                        }
                        break;

                    case 'FOREIGN':
                    # TODO: should we change the category?
                        if ($upper[0] === '(' && substr($upper, -1) === ')') {
                            $processor = new ColumnListProcessor();
                            $expr[] = $processor->process($this->removeParenthesisFromStart($trim));
                            $currCategory = "FOREIGN-COLUMNS";
                        }
                        if ($this->isIndexType($upper)) {
                            $expr[] = array('type' => ExpressionType::INDEX_TYPE, 'base_expr' => $trim);
                        }
                        break;

                    case 'CONSTRAINT':
                    # constraint name
                        $expr[] = array('type' => ExpressionType::CONSTANT, 'base_expr' => $trim);
                        continue 3;

                    case 'KEY':
                    case 'UNIQUE':
                    case 'INDEX':
                    # TODO: should we change the category?
                        if ($upper[0] === '(' && substr($upper, -1) === ')') {
                            $processor = new ColumnListProcessor();
                            $expr[] = $processor->process($this->removeParenthesisFromStart($trim));
                            $currCategory = "INDEX-COLUMNS";
                        }
                        if ($this->isIndexType($upper)) {
                            $expr[] = array('type' => ExpressionType::INDEX_TYPE, 'base_expr' => $trim);
                        }
                        # index name                        
                        $expr[] = array('type' => ExpressionType::CONSTANT, 'base_expr' => $trim);
                        continue 3;
                        break;

                    case 'CHECK':
                        if ($upper[0] === '(' && substr($upper, -1) === ')') {
                            $processor = new ExpressionListProcessor();
                            $unparsed = $this->splitSQLIntoTokens($this->removeParenthesisFromStart($trim));
                            $parsed = $processor->process($unparsed);
                            $expr[] = array('type' => ExpressionType::BRACKET_EXPRESSION, 'base_expr' => $trim,
                                            'sub_tree' => $parsed);
                        }
                        # else?
                        break;

                    default:
                        break;
                    }
                    break;
                }
                $prevCategory = $currCategory;
                $currCategory = "";
            }

            $type = $this->correctExpressionType($expr);
            $result['create-def'][] = array('type' => $type, 'base_expr' => trim($base_expr), 'sub_tree' => $expr);
            return $result;
        }
    }
    define('HAVE_CREATE_DEF_PROCESSOR', 1);
}
