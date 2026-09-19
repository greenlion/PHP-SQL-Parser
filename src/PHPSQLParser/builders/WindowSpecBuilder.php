<?php
/**
 * WindowSpecBuilder.php
 *
 * This file implements the builder for window specifications.
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
 * This class implements the builder for a window specification, that means the
 * part of a window function, which follows the OVER keyword.
 * You can overwrite all functions to achieve another handling.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class WindowSpecBuilder implements Builder {

    protected function buildPartitionBy($parsed) {
        $builder = new WindowPartitionBuilder();
        return $builder->build($parsed);
    }

    protected function buildOrderBy($parsed) {
        $builder = new OrderByBuilder();
        return $builder->build($parsed);
    }

    protected function buildFrame($parsed) {
        $builder = new WindowFrameBuilder();
        return $builder->build($parsed);
    }

    /**
     * Returns the parts of the specification without the surrounding
     * parenthesis.
     */
    protected function buildParts(array $parsed) {
        $parts = array();

        if (!empty($parsed['window_name'])) {
            $parts[] = $parsed['window_name'];
        }
        if (!empty($parsed['partition'])) {
            $parts[] = $this->buildPartitionBy($parsed['partition']);
        }
        if (!empty($parsed['order'])) {
            $parts[] = $this->buildOrderBy($parsed['order']);
        }
        if (!empty($parsed['frame'])) {
            $parts[] = $this->buildFrame($parsed['frame']);
        }

        return implode(" ", $parts);
    }

    /**
     * Within the WINDOW clause the specification always needs the parenthesis,
     * also if it only inherits another window.
     */
    public function buildDefinition(array $parsed) {
        if (!isset($parsed['expr_type']) || $parsed['expr_type'] !== ExpressionType::WINDOW_SPEC) {
            return "";
        }
        return "(" . $this->buildParts($parsed) . ")";
    }

    public function build(array $parsed) {
        if (!isset($parsed['expr_type']) || $parsed['expr_type'] !== ExpressionType::WINDOW_SPEC) {
            return "";
        }

        // a plain reference to a named window needs no parenthesis
        if (!empty($parsed['window_name']) && empty($parsed['partition']) && empty($parsed['order'])
            && empty($parsed['frame'])) {
            return $parsed['window_name'];
        }

        return "(" . $this->buildParts($parsed) . ")";
    }
}
?>
