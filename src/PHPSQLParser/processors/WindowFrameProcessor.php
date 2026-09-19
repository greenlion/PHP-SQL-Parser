<?php
/**
 * WindowFrameProcessor.php
 *
 * This file implements the processor for the frame clause of a window
 * specification.
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
 *
 */

namespace PHPSQLParser\processors;

use PHPSQLParser\utils\ExpressionType;

/**
 * This class processes the frame clause of a window specification, that means
 * the part which starts with ROWS, RANGE or GROUPS:
 *
 *   ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
 *   RANGE UNBOUNDED PRECEDING
 *   GROUPS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING EXCLUDE TIES
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class WindowFrameProcessor extends AbstractProcessor {

    protected static $units = array('ROWS' => true, 'RANGE' => true, 'GROUPS' => true);

    /**
     * True, if the given token starts a frame clause.
     */
    public static function isFrameUnit($upper) {
        return isset(self::$units[$upper]);
    }

    protected function processExpressionList($tokens) {
        $processor = new ExpressionListProcessor($this->options);
        return $processor->process($tokens);
    }

    /**
     * Parses a single frame bound, e.g. "UNBOUNDED PRECEDING", "CURRENT ROW",
     * "2 PRECEDING" or "INTERVAL 5 DAY FOLLOWING".
     */
    protected function processBound($tokens) {
        $base_expr = trim(implode('', $tokens));
        if ($base_expr === '') {
            return false;
        }

        // strip the whitespace tokens, but keep the original order
        $stripped = array();
        foreach ($tokens as $token) {
            if (!$this->isWhitespaceToken($token)) {
                $stripped[] = $token;
            }
        }

        $result = array('expr_type' => ExpressionType::WINDOW_FRAME_BOUND, 'base_expr' => $base_expr,
                        'direction' => false, 'unbounded' => false, 'value' => false);

        $upper = strtoupper(trim(implode(' ', $stripped)));
        if ($upper === 'CURRENT ROW') {
            $result['direction'] = 'CURRENT ROW';
            return $result;
        }

        // the last token defines the direction of the bound
        $direction = strtoupper(trim(array_pop($stripped)));
        if ($direction !== 'PRECEDING' && $direction !== 'FOLLOWING') {
            // we don't understand the bound, keep it as an expression
            $stripped[] = $direction;
            $result['value'] = $this->processValue($stripped);
            return $result;
        }
        $result['direction'] = $direction;

        if (count($stripped) === 1 && strtoupper(trim($stripped[0])) === 'UNBOUNDED') {
            $result['unbounded'] = true;
            return $result;
        }

        $result['value'] = $this->processValue($stripped);
        return $result;
    }

    /**
     * The offset of a frame bound can be a literal, but also an expression
     * like INTERVAL 5 DAY.
     */
    protected function processValue($tokens) {
        if (empty($tokens)) {
            return false;
        }

        $base_expr = trim(implode(' ', $tokens));
        $parsed = $this->processExpressionList(array_values($tokens));

        if (count($parsed) === 1) {
            return $parsed[0];
        }

        return array('expr_type' => ExpressionType::EXPRESSION, 'base_expr' => $base_expr, 'sub_tree' => $parsed);
    }

    /**
     * @param array $tokens the token list of the frame clause, it starts with
     *                      the frame unit (ROWS, RANGE or GROUPS)
     */
    public function process($tokens) {
        if (empty($tokens)) {
            return false;
        }

        $result = array('expr_type' => ExpressionType::WINDOW_FRAME, 'base_expr' => trim(implode('', $tokens)),
                        'unit' => false, 'start' => false, 'end' => false, 'exclude' => false);

        $unitFound = false;
        $between = false;
        $current = array();
        $bounds = array();
        $exclude = array();

        foreach ($tokens as $token) {
            $upper = strtoupper(trim($token));

            if (!$unitFound) {
                if ($this->isWhitespaceToken($token)) {
                    continue;
                }
                $result['unit'] = $upper;
                $unitFound = true;
                continue;
            }

            if (!empty($exclude) || $upper === 'EXCLUDE') {
                $exclude[] = $token;
                continue;
            }

            if ($upper === 'BETWEEN' && empty($bounds) && trim(implode('', $current)) === '') {
                $between = true;
                continue;
            }

            // within a BETWEEN the AND separates both bounds, the AND of an
            // expression like "INTERVAL 1 DAY" cannot occur here
            if ($between && $upper === 'AND' && count($bounds) === 0) {
                $bounds[] = $current;
                $current = array();
                continue;
            }

            $current[] = $token;
        }

        $bounds[] = $current;

        $result['start'] = $this->processBound($bounds[0]);
        if ($between && isset($bounds[1])) {
            $result['end'] = $this->processBound($bounds[1]);
        }

        if (!empty($exclude)) {
            // EXCLUDE CURRENT ROW | GROUP | TIES | NO OTHERS
            $parts = array();
            foreach ($exclude as $token) {
                if (!$this->isWhitespaceToken($token)) {
                    $parts[] = strtoupper(trim($token));
                }
            }
            array_shift($parts); // remove the EXCLUDE itself
            $result['exclude'] = implode(' ', $parts);
        }

        return $result;
    }
}
?>
