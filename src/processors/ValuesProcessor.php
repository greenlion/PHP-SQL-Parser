<?php
/**
 * ValuesProcessor.php
 *
 * This file implements the processor for the VALUES statements.
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

require_once dirname(__FILE__) . '/../utils/ExpressionType.php';
require_once dirname(__FILE__) . '/RecordProcessor.php';
require_once dirname(__FILE__) . '/AbstractProcessor.php';

/**
 * 
 * This class processes the VALUES statements.
 * 
 * @author arothe
 * 
 */
class ValuesProcessor extends AbstractProcessor {

    private $recordProcessor;

    public function __construct() {
        $this->recordProcessor = new RecordProcessor();
    }

    public function process($tokens) {

        $currCategory = '';
        $parsed = array();
        $base_expr = '';

        foreach ($tokens['VALUES'] as $k => $v) {
            $base_expr .= $v;
            $trim = trim($v);

            if ($this->isWhitespaceToken($v)) {
                continue;
            }

            $upper = strtoupper($trim);
            switch ($upper) {

            case 'ON':
                if ($currCategory === '') {

                    $processor = new RecordProcessor();
                    $base_expr = trim(substr($base_expr, 0, -strlen($v)));
                    $parsed[] = array('expr_type' => ExpressionType::RECORD, 'base_expr' => $base_expr,
                                      'data' => $this->recordProcessor->process($base_expr), 'delim' => false);
                    $base_expr = '';

                    $currCategory = 'DUPLICATE';
                    $parsed[] = array('expr_type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                }
                // else ?
                break;

            case 'DUPLICATE':
            case 'KEY':
            case 'UPDATE':
                if ($currCategory === 'DUPLICATE') {
                    $parsed[] = array('expr_type' => ExpressionType::RESERVED, 'base_expr' => $trim);
                    $base_expr = '';
                }
                // else ?
                break;

            case ',':
                if ($currCategory === 'DUPLICATE') {

                    $processor = new ExpressionListProcessor();
                    $base_expr = trim(substr($base_expr, 0, -strlen($v)));
                    $parsed[] = array('expr_type' => ExpressionType::EXPRESSION, 'base_expr' => $base_expr,
                                      'sub_tree' => $processor->process($this->splitSQLIntoTokens($base_expr)),
                                      'delim' => $trim);
                    $base_expr = '';
                    continue 2;
                }

                $processor = new RecordProcessor();
                $parsed[] = array('expr_type' => ExpressionType::RECORD, 'base_expr' => trim($base_expr),
                                  'data' => $this->recordProcessor->process(trim($base_expr)), 'delim' => $trim);
                $base_expr = '';
                break;

            default:
                break;
            }

        }

        if (trim($base_expr) !== '') {
            if ($currCategory === '') {
                $processor = new RecordProcessor();
                $parsed[] = array('expr_type' => ExpressionType::RECORD, 'base_expr' => trim($base_expr),
                                  'data' => $this->recordProcessor->process(trim($base_expr)), 'delim' => false);
            }
            if ($currCategory === 'DUPLICATE') {
                $processor = new ExpressionListProcessor();
                $parsed[] = array('expr_type' => ExpressionType::EXPRESSION, 'base_expr' => trim($base_expr),
                                  'sub_tree' => $processor->process($this->splitSQLIntoTokens($base_expr)),
                                  'delim' => false);
            }
        }

        $tokens['VALUES'] = $parsed;
        return $tokens;
    }

}
?>