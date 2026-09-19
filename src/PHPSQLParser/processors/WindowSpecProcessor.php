<?php
/**
 * WindowSpecProcessor.php
 *
 * This file implements the processor for a window specification, that means
 * the part of a window function which follows the OVER keyword.
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
 * This class processes a window specification:
 *
 *   window_spec: [window_name] [PARTITION BY ...] [ORDER BY ...] [frame_clause]
 *
 * A window function can reference a window which has been defined within the
 * WINDOW clause of the statement (OVER w). Such a reference can also add an
 * ORDER BY or a frame clause to the referenced window (OVER (w ORDER BY x)),
 * therefore the [window_name] is a field of the specification and not a
 * replacement for it.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class WindowSpecProcessor extends AbstractProcessor {

    protected function processSelectExpression($unparsed) {
        $processor = new SelectExpressionProcessor($this->options);
        return $processor->process($unparsed);
    }

    protected function processOrderBy($tokens) {
        $processor = new OrderByProcessor($this->options);
        return $processor->process($tokens);
    }

    protected function processFrame($tokens) {
        $processor = new WindowFrameProcessor($this->options);
        return $processor->process($tokens);
    }

    /**
     * The PARTITION BY clause holds a comma separated list of expressions.
     */
    protected function processPartitionBy($tokens) {
        $out = array();
        $base_expr = "";

        foreach ($tokens as $token) {
            if (trim($token) === ',') {
                $out[] = $this->processPartitionExpression($base_expr);
                $base_expr = "";
                continue;
            }
            $base_expr .= $token;
        }
        $out[] = $this->processPartitionExpression($base_expr);

        $out = array_values(array_filter($out));
        return empty($out) ? false : $out;
    }

    protected function processPartitionExpression($base_expr) {
        $base_expr = trim($base_expr);
        if ($base_expr === "") {
            return false;
        }

        $expr = $this->processSelectExpression($base_expr);
        // a partition expression cannot have an alias
        unset($expr['alias']);
        return $expr;
    }

    /**
     * Processes the token, which follows the OVER keyword. It is either the
     * name of a window defined within the WINDOW clause or a parenthesized
     * window specification.
     *
     * @param string $token the raw token after the OVER keyword
     */
    public function process($token) {
        $trim = trim($token);

        $result = array('expr_type' => ExpressionType::WINDOW_SPEC, 'base_expr' => $trim, 'window_name' => false,
                        'partition' => false, 'order' => false, 'frame' => false);

        if ($trim === "") {
            return $result;
        }

        if ($trim[0] !== '(' || substr($trim, -1) !== ')') {
            // OVER w, a reference to a named window
            $result['window_name'] = $trim;
            $result['no_quotes'] = $this->revokeQuotation($trim);
            return $result;
        }

        $inner = $this->removeParenthesisFromStart($trim);
        return $this->processSpecification($this->splitSQLIntoTokens($inner), $result);
    }

    /**
     * Splits the body of a window specification into its sections and
     * processes each of them.
     *
     * @param array $tokens the token list of the specification body
     * @param array $result the initialized output array
     */
    public function processSpecification($tokens, $result) {
        $section = 'NAME';
        $awaitBy = false;
        $parts = array('NAME' => array(), 'PARTITION' => array(), 'ORDER' => array(), 'FRAME' => array());

        foreach ($tokens as $token) {
            $upper = strtoupper(trim($token));

            // the frame clause is the last section, everything after it belongs to it
            if ($section !== 'FRAME') {
                if ($upper === 'PARTITION') {
                    $section = 'PARTITION';
                    $awaitBy = true;
                    continue;
                }
                if ($upper === 'ORDER') {
                    $section = 'ORDER';
                    $awaitBy = true;
                    continue;
                }
                if (WindowFrameProcessor::isFrameUnit($upper)) {
                    $section = 'FRAME';
                    $parts['FRAME'][] = $token;
                    continue;
                }
            }

            if ($awaitBy) {
                if ($this->isWhitespaceToken($token)) {
                    continue;
                }
                // the BY of PARTITION BY and ORDER BY carries no information
                $awaitBy = false;
                if ($upper === 'BY') {
                    continue;
                }
            }

            $parts[$section][] = $token;
        }

        $name = trim(implode('', $parts['NAME']));
        if ($name !== "") {
            $result['window_name'] = $name;
            $result['no_quotes'] = $this->revokeQuotation($name);
        }

        if (!empty($parts['PARTITION'])) {
            $result['partition'] = $this->processPartitionBy($parts['PARTITION']);
        }

        if (!empty($parts['ORDER'])) {
            $result['order'] = $this->processOrderBy(array_values($parts['ORDER']));
        }

        if (!empty($parts['FRAME'])) {
            $result['frame'] = $this->processFrame(array_values($parts['FRAME']));
        }

        return $result;
    }
}
?>
