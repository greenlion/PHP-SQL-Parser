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
if (!defined('HAVE_COL_DEF_PROCESSOR')) {
    require_once(dirname(__FILE__) . '/abstract-processor.php');
    require_once(dirname(__FILE__) . '/../expression-types.php');

    /**
     * 
     * This class processes the column definitions of the TABLE statements.
     * 
     * @author arothe
     * 
     */
    class ColDefProcessor extends AbstractProcessor {

        public function process($tokens) {

            $createDef = array();
            $prevCategory = "";
            $currCategory = "";
            $expr = array();
            $result = array();

            foreach ($tokens as $k => $token) {

                $trim = trim($token);
                if ($trim === "") {
                    $createDef[] = $token;
                    continue;
                }

                $upper = strtoupper($trim);

                switch ($upper) {

                case 'CONSTRAINT':
                case 'LIKE':
                    $currCategory = $prevCategory = $upper;
                    continue 2;

                case 'FOREIGN':
                case 'PRIMARY':
                case 'UNIQUE':
                    $currCategory = $upper;
                    if ($prevCategory === "" || $prevCategory === "CONSTRAINT") {
                        # next one is KEY
                        continue 2;
                    }
                    break;

                case 'KEY':
                # the next one is an index name
                    if ($currCategory === 'PRIMARY' && $currCategory === 'FOREIGN') {
                        continue 2;
                    }
                    $currCategory = $upper;
                    continue 2;

                case 'CHECK':
                case 'INDEX':
                    if ($currCategory === 'UNIQUE') {
                        # index after unique
                        continue 2;
                    }
                    $currCategory = $upper;
                    continue 2;

                case 'FULLTEXT':
                case 'SPATIAL':
                    $currCategory = $prevCategory = $upper;
                    continue 2;

                case 'WITH':
                    break;

                case 'PARSER':
                    break;
                    
                case ',':
                    # this starts the next definition
                    break;

                default:
                    switch ($currCategory) {

                    case 'LIKE':
                    # this is the tablename after LIKE
                        $result['like'] = array('table' => $trim, 'base_expr' => $trim,
                                                'no_quotes' => $this->revokeQuotation($trim));

                        break;

                    case 'PRIMARY':
                    # this could be the index_type BTREE or HASH
                    # or if we have parenthesis, then we have the list of index_columns
                        break;

                    case 'FOREIGN':
                    # this could be the index_name
                    # or if we have parenthesis, then we have the list of index_columns
                        break;

                    case 'CONSTRAINT':
                    # this is the constraint symbol
                        break;

                    case 'INDEX':
                    # this is the index name (after SPATIAL, FULLTEXT, INDEX)
                        break;

                    case 'CHECK':
                    # if we have parenthesis, then we have the check expression
                        break;

                    case 'KEY':
                    # this could be the index_name or index_type (BTREE or HASH)
                        break;

                    case 'UNIQUE':
                    # this could be the index_name or index_type (BTREE or HASH)
                        break;

                    default:
                        break;
                    }
                    break;
                }
                $prevCategory = currCategory;
                $currCategory = "";

            }
            return $result;
        }
    }
    define('HAVE_COL_DEF_PROCESSOR', 1);
}
