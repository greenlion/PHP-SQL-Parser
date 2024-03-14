<?php
/**
 * TableProcessor.php
 *
 * This file implements the processor for the TABLE statements.
 *
 * PHP version 5
 *
 * LICENSE:
 * Copyright (c) 2010-2014 Justin Swanhart and André Rothe
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author    André Rothe <andre.rothe@phosco.info>
 * @copyright 2010-2014 Justin Swanhart and André Rothe
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @version   SVN: $Id$
 *
 */

namespace PHPSQLParser\processors;

use PHPSQLParser\utils\ExpressionType;

/**
 * This class processes the TABLE statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class SourceProcessor extends AbstractProcessor
{

    protected function getReservedType($token)
    {
        return array('expr_type' => ExpressionType::RESERVED, 'base_expr' => $token);
    }

    protected function getConstantType($token)
    {
        return array('expr_type' => ExpressionType::CONSTANT, 'base_expr' => $token);
    }

    protected function getOperatorType($token)
    {
        return array('expr_type' => ExpressionType::OPERATOR, 'base_expr' => $token);
    }

    protected function processPartitionOptions($tokens)
    {
        $processor = new PartitionOptionsProcessor($this->options);
        return $processor->process($tokens);
    }

    protected function processCreateDefinition($tokens)
    {
        $processor = new CreateDefinitionProcessor($this->options);
        return $processor->process($tokens);
    }

    protected function clear(&$expr, &$base_expr, &$category)
    {
        $expr = array();
        $base_expr = '';
        $category = 'CREATE_DEF';
    }

    public function process($tokens)
    {

        $currCategory = 'TABLE_NAME';
        $result = array('base_expr' => false, 'name' => false, 'no_quotes' => false, 'create-def' => false,
            'options' => array(), 'like' => false, 'select-option' => false);
        $expr = array();
        $base_expr = '';
        $skip = 0;

        foreach ($tokens as $tokenKey => $token) {
            $trim = trim($token);
            $base_expr .= $token;

            if ($skip > 0) {
                $skip--;
                continue;
            }

            if ($skip < 0) {
                break;
            }

            if ($trim === '') {
                continue;
            }

            $upper = strtoupper($trim);
            switch ($upper) {

                case ',':
                    // it is possible to separate the table options with comma!
                    if ($prevCategory === 'CREATE_DEF') {
                        $last = array_pop($result['options']);
                        $last['delim'] = ',';
                        $result['options'][] = $last;
                        $base_expr = '';
                    }
                    continue 2;

                case '=':
                    // the optional operator
                    if ($prevCategory === 'TABLE_OPTION') {
                        $expr[] = $this->getOperatorType($trim);
                        continue 2; // don't change the category
                    }
                    break;

                case 'CHARACTER':
                    if ($prevCategory === 'CREATE_DEF') {
                        $expr[] = $this->getReservedType($trim);
                        $currCategory = 'TABLE_OPTION';
                    }
                    if ($prevCategory === 'TABLE_OPTION') {
                        // add it to the previous DEFAULT
                        $expr[] = $this->getReservedType($trim);
                        continue 2;
                    }
                    break;

                case 'ENGINE':
                case 'TYPE':
                case 'BROKER_LIST':
                case 'TOPIC_LIST':
                case 'CONSUMER_GROUP':
                case 'NUM_CONSUMERS':
                    if ($prevCategory === 'CREATE_DEF') {
                        $expr[] = $this->getReservedType($trim);
                        $currCategory = $prevCategory = 'TABLE_OPTION';
                        continue 2;
                    }
                    break;

                case 'TO':
                    $currCategory = 'TO_TABLE_NAME';
                    continue 2;

                default:
                    switch ($currCategory) {
                        case 'TABLE_NAME':
                            $result['base_expr'] = $result['name'] = $trim;
                            $result['no_quotes'] = $this->revokeQuotation($trim);
                            $this->clear($expr, $base_expr, $prevCategory);
                            break;

                        case 'TO_TABLE_NAME':
                            $result['to'] = ['expr_type' => ExpressionType::TABLE, 'table' => $trim,
                                'base_expr' => $trim, 'no_quotes' => $this->revokeQuotation($trim)];;
                            break;
                        case '':
                            // after table name
                            if ($prevCategory === 'TABLE_NAME' && $upper[0] === '(' && substr($upper, -1) === ')') {
                                $unparsed = $this->splitSQLIntoTokens($this->removeParenthesisFromStart($trim));
                                $coldef = $this->processCreateDefinition($unparsed);
                                $result['create-def'] = array('expr_type' => ExpressionType::BRACKET_EXPRESSION,
                                    'base_expr' => $base_expr, 'sub_tree' => $coldef['create-def']);
                                $expr = array();
                                $base_expr = '';
                                $currCategory = 'CREATE_DEF';
                            }
                            break;

                        default:
                            // strings and numeric constants
                            $expr[] = $this->getConstantType($trim);
                            $result['options'][] = array('expr_type' => ExpressionType::EXPRESSION,
                                'base_expr' => trim($base_expr), 'delim' => ' ', 'sub_tree' => $expr);
                            $this->clear($expr, $base_expr, $currCategory);
                            break;
                    }
                    break;
            }

            $prevCategory = $currCategory;
            $currCategory = '';
        }

        if ($result['like'] === false) {
            unset($result['like']);
        }
        if ($result['select-option'] === false) {
            unset($result['select-option']);
        }
        if ($result['options'] === array()) {
            $result['options'] = false;
        }

        return $result;
    }
}

?>