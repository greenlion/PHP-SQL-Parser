<?php
/**
 * WindowFunctionBuilder.php
 *
 * This file implements the builder for window functions.
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
use PHPSQLParser\exceptions\UnableToCreateSQLException;
use PHPSQLParser\utils\ExpressionType;

/**
 * This class implements the builder for window functions, that means a
 * function call followed by an OVER clause.
 * You can overwrite all functions to achieve another handling.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class WindowFunctionBuilder implements Builder {

    protected function buildAlias($parsed) {
        $builder = new AliasBuilder();
        return $builder->build($parsed);
    }

    protected function buildFunction($parsed) {
        $builder = new FunctionBuilder();
        return $builder->build($parsed);
    }

    protected function buildWindowSpec($parsed) {
        $builder = new WindowSpecBuilder();
        return $builder->build($parsed);
    }

    protected function buildDirection($parsed) {
        $builder = new DirectionBuilder();
        return $builder->build($parsed);
    }

    public function build(array $parsed) {
        if (!isset($parsed['expr_type']) || $parsed['expr_type'] !== ExpressionType::WINDOW_FUNCTION) {
            return "";
        }

        $sql = "";
        foreach ($parsed['sub_tree'] as $k => $v) {
            $len = strlen($sql);

            if (isset($v['expr_type']) && $v['expr_type'] === ExpressionType::WINDOW_SPEC) {
                if (!empty($parsed['null_treatment'])) {
                    $sql .= " " . $parsed['null_treatment'];
                }
                $sql .= " OVER " . $this->buildWindowSpec($v);
            } else {
                $sql .= $this->buildFunction($v);
            }

            if ($len == strlen($sql)) {
                throw new UnableToCreateSQLException('window function', $k, $v, 'expr_type');
            }
        }

        $sql .= $this->buildAlias($parsed);
        $sql .= $this->buildDirection($parsed);
        return $sql;
    }
}
?>
