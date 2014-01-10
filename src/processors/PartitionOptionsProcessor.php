<?php
/**
 * PartitionOptions.php
 *
 * This file implements the processor for the PARTITION BY statements 
 * within CREATE TABLE.
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
 * @version   SVN: $Id: CreateDefinitionProcessor.php 944 2014-01-08 20:03:21Z phosco@gmx.de $
 *
 */

require_once dirname(__FILE__) . '/AbstractProcessor.php';
require_once dirname(__FILE__) . '/../utils/ExpressionType.php';

/**
 * This class processes the PARTITION BY statements within CREATE TABLE.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *  
 */
class PartitionOptionsProcessor extends AbstractProcessor {

    public function process($tokens) {

        $prevCategory = '';
        $currCategory = '';
        $result = array();
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

            case 'PARTITION':
                if ($currCategory === 'PARTITION') {
                    // TODO: delegate the rest of the $tokens to the PartitionDefinitionProcessor
                    break;                    
                }
            case 'SUBPARTITION':
                $currCategory = $upper;
                $skip = 1; // skip BY keyword
                // TODO: store it
                break;

            case 'PARTITIONS':
            case 'SUBPARTITIONS':
                $currCategory = 'PARTITION_NUM';
                // TODO: store it
                continue 2;

            case 'LINEAR':
            // followed by HASH or KEY
            // TODO: store it as reserved
                continue 2;

            case 'HASH':
                $currCategory = $upper;
                // TODO: store it
                continue 2;

            case 'KEY':
                $currCategory = $upper;
                // TODO: store it
                continue 2;

            case 'ALGORITHM':
                if ($currCategory === 'KEY') {
                    $currCategory = $upper;
                    // TODO: store it
                    continue 2;
                }
                break;

            case 'RANGE':
            case 'LIST':
                $currCategory = $upper . '_EXPR';
                // TODO: store it
                continue 2;

            case 'COLUMNS':
                if ($currCategory === 'RANGE_EXPR' || $currCategory === 'LIST_EXPR') {
                    $currCategory = substr($currCategory, 0, 5) . $upper;
                    // TODO: store it as reserved
                    continue 2;
                }
                break;

            case '=':
                if ($currCategory === 'ALGORITHM') {
                    // between ALGORITHM and a constant
                    // TODO: store it
                    continue 2;
                }
                break;

            default:
                switch ($currCategory) {

                case 'PARTITION_NUM':
                // the number behind PARTITIONS or SUBPARTITIONS
                // TODO: store it
                    $currCategory = $prevCategory;
                    break;

                case 'HASH':
                // parenthesis around an expression
                // TODO: store it
                    $currCategory = $prevCategory;
                    break;

                case 'ALGORITHM':
                // the number of the algorithm
                // TODO: store it
                    $currCategory = 'KEY';
                    continue 3;

                case 'KEY':
                // the columnlist 
                // TODO: store it
                    $currCategory = $prevCategory;
                    break;

                case 'LIST_EXPR':
                case 'RANGE_EXPR':
                // the expression right after RANGE or LIST
                // TODO: store it
                    $currCategory = $prevCategory;
                    break;

                case 'LIST_COLUMNS':
                case 'RANGE_COLUMNS':
                // the columnlist 
                // TODO: store it
                    $currCategory = $prevCategory;
                    break;

                default:
                    break;
                }
                break;
            }

            $prevCategory = $currCategory;
            $currCategory = '';
        }

        if (!isset($result['till'])) {
            // FIXME: set the real read marker within the $tokens array
            $result['till'] = 0;
        }
        return $result;
    }
}
?>