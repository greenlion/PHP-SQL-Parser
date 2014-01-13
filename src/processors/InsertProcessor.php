<?php
/**
 * InsertProcessor.php
 *
 * This file implements the processor for the INSERT statements. 
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

require_once dirname(__FILE__) . '/AbstractProcessor.php';
require_once dirname(__FILE__) . '/DefaultProcessor.php';
require_once dirname(__FILE__) . '/ColumnListProcessor.php';
require_once dirname(__FILE__) . '/../utils/ExpressionType.php';

/**
 * This class processes the INSERT statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *  
 */
class InsertProcessor extends AbstractProcessor {

    public function process($tokenList, $token_category = 'INSERT') {
        $table = "";
        $cols = false;
        $parsed = array();

        $into = $tokenList['INTO'];
        foreach ($into as $token) {
            $trim = trim($token);

            if ($trim === '') {
                continue;
            }

            if ($table === "") {
                $table = $trim;
                continue;
            }

            if ($cols === false) {
                $cols = $trim;
            }
        }

        $tokenList[$token_category] = array();
        unset($tokenList['INTO']);
        unset($into);

        if ($cols !== false) {
            if ($cols[0] === '(' && substr($cols, -1) === ')') {
                $parsed = array('expr_type' => false, 'base_expr' => $cols, 'sub_tree' => false);
            }
            $cols = $this->removeParenthesisFromStart($cols);
            if (stripos($cols, 'SELECT') === 0) {
                $processor = new DefaultProcessor();
                $parsed['sub_tree'] = $processor->process($cols);
                $parsed['expr_type'] = ExpressionType::SUBQUERY;
            } else {
                $processor = new ColumnListProcessor();
                $parsed['sub_tree'] = $processor->process($cols);
                $parsed['expr_type'] = ExpressionType::COLUMN_LIST;
            }
        }

        $tokenList[$token_category][] = array('expr_type' => ExpressionType::TABLE, 'table' => $table, 'base_expr' => $table,
                                              'no_quotes' => $this->revokeQuotation($table));
        if (!empty($parsed)) {
            $tokenList[$token_category][] = $parsed;
        }

        return $tokenList;
    }

}
?>
