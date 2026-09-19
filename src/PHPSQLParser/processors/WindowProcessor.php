<?php
/**
 * WindowProcessor.php
 *
 * This file implements the processor for the WINDOW clause.
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
 * This class processes the WINDOW clause, which defines named windows:
 *
 *   WINDOW w AS (PARTITION BY a ORDER BY b), w2 AS (w ROWS UNBOUNDED PRECEDING)
 *
 * Every definition holds the defined name within [window_name] and the
 * window specification within [spec]. The specification uses the same format
 * as an inline OVER clause, so a named window and an inline window can be
 * processed by the same code.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class WindowProcessor extends AbstractProcessor {

    protected function processWindowSpec($token) {
        $processor = new WindowSpecProcessor($this->options);
        return $processor->process($token);
    }

    /**
     * Processes a single "name AS (spec)" definition.
     */
    protected function processDefinition($tokens) {
        $base_expr = trim(implode('', $tokens));
        if ($base_expr === "") {
            return false;
        }

        $name = false;
        $spec = "";

        foreach ($tokens as $token) {
            $trim = trim($token);
            if ($trim === "") {
                continue;
            }
            if ($name === false) {
                $name = $trim;
                continue;
            }
            if (strtoupper($trim) === 'AS' && $spec === "") {
                continue;
            }
            $spec = $token;
        }

        $result = array('expr_type' => ExpressionType::WINDOW_DEF, 'base_expr' => $base_expr,
                        'window_name' => $name);
        if ($name !== false) {
            $result['no_quotes'] = $this->revokeQuotation($name);
        }
        $result['spec'] = $this->processWindowSpec($spec);
        return $result;
    }

    public function process($tokens) {
        if (!$tokens) {
            return false;
        }

        $out = array();
        $chunk = array();

        foreach ($tokens as $token) {
            if ($this->isCommaToken($token)) {
                $out[] = $this->processDefinition($chunk);
                $chunk = array();
                continue;
            }
            $chunk[] = $token;
        }
        $out[] = $this->processDefinition($chunk);

        $out = array_values(array_filter($out));
        return empty($out) ? false : $out;
    }
}
?>
