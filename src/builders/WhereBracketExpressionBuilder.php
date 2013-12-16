<?php
/**
 * WhereBracketExpressionBuilder.php
 *
 * Builds bracket expressions within the WHERE part.
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
 * @version   SVN: $Id: CheckBuilder.php 764 2013-12-16 11:19:30Z phosco@gmx.de $
 * 
 */

require_once dirname(__FILE__) . '/../utils/ExpressionType.php';
require_once dirname(__FILE__) . '/../exceptions/UnableToCreateSQLException.php';
require_once dirname(__FILE__) . '/ColumnReferenceBuilder.php';
require_once dirname(__FILE__) . '/ConstantBuilder.php';
require_once dirname(__FILE__) . '/OperatorBuilder.php';
require_once dirname(__FILE__) . '/FunctionBuilder.php';
require_once dirname(__FILE__) . '/InListBuilder.php';
require_once dirname(__FILE__) . '/WhereExpressionBuilder.php';
require_once dirname(__FILE__) . '/WhereBracketExpressionBuilder.php';
require_once dirname(__FILE__) . '/UserVariableBuilder.php';

/**
 * This class implements the builder for bracket expressions within the WHERE part. 
 * You can overwrite all functions to achive another handling.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *  
 */
class WhereBracketExpressionBuilder {

    protected function buildColRef($parsed) {
        $builder = new ColumnReferenceBuilder($parsed);
        return $builder->build($parsed);
    }

    protected function buildConstant($parsed) {
        $builder = new ConstantBuilder($parsed);
        return $builder->build($parsed);
    }
    
    protected function buildOperator($parsed) {
        $builder = new OperatorBuilder($parsed);
        return $builder->build($parsed);
    }
    
    protected function buildFunction($parsed) {
        $builder = new FunctionBuilder($parsed);
        return $builder->build($parsed);
    }
    
    protected function buildInList($parsed) {
        $builder = new InListBuilder($parsed);
        return $builder->build($parsed);
    }
    
    protected function buildWhereExpression($parsed) {
        $builder = new WhereExpressionBuilder($parsed);
        return $builder->build($parsed);
    }
    
    protected function buildWhereBracketExpression($parsed) {
        $builder = new WhereBracketExpressionBuilder($parsed);
        return $builder->build($parsed);
    }
    
    protected function buildUserVariable($parsed) {
        $builder = new UserVariableBuilder($parsed);
        return $builder->build($parsed);
    }
    
    public function build($parsed) {
        if ($parsed['expr_type'] !== ExpressionType::EXPRESSION) {
            return "";
        }
        $sql = "";
        foreach ($parsed['sub_tree'] as $k => $v) {
            $len = strlen($sql);
            $sql .= $this->processColRef($v);
            $sql .= $this->processConstant($v);
            $sql .= $this->processOperator($v);
            $sql .= $this->processInList($v);
            $sql .= $this->processFunction($v);
            $sql .= $this->processWhereExpression($v);
            $sql .= $this->processWhereBracketExpression($v);
            $sql .= $this->processUserVariable($v);

            if ($len == strlen($sql)) {
                throw new UnableToCreateSQLException('WHERE expression subtree', $k, $v, 'expr_type');
            }

            $sql .= " ";
        }

        $sql = substr($sql, 0, -1);
        return $sql;
    }
    
}
?>
