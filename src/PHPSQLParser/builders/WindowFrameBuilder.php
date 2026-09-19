<?php
/**
 * WindowFrameBuilder.php
 *
 * This file implements the builder for the frame clause of a window specification.
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

namespace PHPSQLParser\builders;
use PHPSQLParser\utils\ExpressionType;

/**
 * This class implements the builder for the frame clause of a window
 * specification, e.g. ROWS BETWEEN 2 PRECEDING AND CURRENT ROW.
 * You can overwrite all functions to achieve another handling.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class WindowFrameBuilder implements Builder {

    protected function buildColRef($parsed) {
        $builder = new ColumnReferenceBuilder();
        return $builder->build($parsed);
    }

    protected function buildConstant($parsed) {
        $builder = new ConstantBuilder();
        return $builder->build($parsed);
    }

    protected function buildFunction($parsed) {
        $builder = new FunctionBuilder();
        return $builder->build($parsed);
    }

    protected function buildSelectExpression($parsed) {
        $builder = new SelectExpressionBuilder();
        return $builder->build($parsed);
    }

    protected function buildSelectBracketExpression($parsed) {
        $builder = new SelectBracketExpressionBuilder();
        return $builder->build($parsed);
    }

    /**
     * The offset of a frame bound can be a literal, but also an expression
     * such as INTERVAL 5 DAY.
     */
    protected function buildValue($parsed) {
        if (empty($parsed)) {
            return "";
        }

        $sql = $this->buildConstant($parsed);
        $sql .= $this->buildColRef($parsed);
        $sql .= $this->buildFunction($parsed);
        $sql .= $this->buildSelectBracketExpression($parsed);
        $sql .= $this->buildSelectExpression($parsed);

        if ($sql === "" && isset($parsed['base_expr'])) {
            $sql = $parsed['base_expr'];
        }
        return $sql;
    }

    public function buildBound($parsed) {
        if (empty($parsed) || !isset($parsed['expr_type'])
            || $parsed['expr_type'] !== ExpressionType::WINDOW_FRAME_BOUND) {
            return "";
        }

        if ($parsed['direction'] === 'CURRENT ROW') {
            return 'CURRENT ROW';
        }

        $sql = "";
        if (!empty($parsed['unbounded'])) {
            $sql = 'UNBOUNDED';
        } elseif (!empty($parsed['value'])) {
            $sql = $this->buildValue($parsed['value']);
        }

        if (!empty($parsed['direction'])) {
            $sql .= ($sql === "" ? "" : " ") . $parsed['direction'];
        }
        return $sql;
    }

    public function build(array $parsed) {
        if (!isset($parsed['expr_type']) || $parsed['expr_type'] !== ExpressionType::WINDOW_FRAME) {
            return "";
        }

        $sql = $parsed['unit'];

        if (!empty($parsed['end'])) {
            $sql .= " BETWEEN " . $this->buildBound($parsed['start']);
            $sql .= " AND " . $this->buildBound($parsed['end']);
        } else {
            $sql .= " " . $this->buildBound($parsed['start']);
        }

        if (!empty($parsed['exclude'])) {
            $sql .= " EXCLUDE " . $parsed['exclude'];
        }
        return $sql;
    }
}
?>
