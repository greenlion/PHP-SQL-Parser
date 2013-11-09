<?php
/**
 * table-processor.php
 *
 * This file implements the processor for the TABLE statements.
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
if (!defined('HAVE_TABLE_PROCESSOR')) {
    require_once(dirname(__FILE__) . '/abstract-processor.php');
    require_once(dirname(__FILE__) . '/col-def-processor.php');
    require_once(dirname(__FILE__) . '/../expression-types.php');

    /**
     * 
     * This class processes the TABLE statements.
     * 
     * @author arothe
     * 
     */
    class TableProcessor extends AbstractProcessor {

        public function process($tokens) {

            $curr = "TABLENAME";
            $expr = array();

            foreach ($tokens as $token) {
                $trim = trim($token);

                if ($trim === "") {
                    continue;
                }

                $upper = strtoupper($trim);

                if ($upper === 'LIKE') {
                    # like without parenthesis
                    $curr = $upper;
                    continue;
                }

                if ($curr === "TABLENAME") {
                    $expr['base_expr'] = $expr['name'] = $trim;
                    $expr['no_quotes'] = $this->revokeQuotation($trim);
                    $curr = "";
                    continue;
                }

                if ($curr === "LIKE") {
                    $expr['like'] = array('table' => $trim, 'base_expr' => $trim, 'no_quotes' => $this->revokeQuotation($trim));
                    $curr = "";
                    continue;
                }
                
                if ($upper[0] === '(' && substr($upper, -1) === ')') {
                    $unparsed = $this->splitSQLIntoTokens($this->removeParenthesisFromStart($trim));
                    $processor = new ColDefProcessor();
                    $coldef = $processor->process($unparsed);

                    foreach ($coldef as $k => $v) {
                        if (isset($v['type'])) {
                            $type = $v['type'];
                            unset($v['type']);
                            if ($type === ExpressionType::COLDEF) {
                                $expr[$type][] = $v;
                            } else {
                                if (!isset($expr[$type])) {
                                    $expr[$type] = array();
                                }
                                $expr[$type][] = $v;
                            }
                        }
                    }

                    # TODO:
                    # after a () we can have multiple table_options and a select_statement
                    # but only if we don't have set $expr['like'] inside the parenthesis
                }

            }
            return $expr;
        }
    }
    define('HAVE_TABLE_PROCESSOR', 1);
}
