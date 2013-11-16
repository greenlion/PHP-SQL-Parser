<?php
/**
 * sql-expression-processor.php
 *
 * This file implements the processor for the SQL chunks.
 *
 * Copyright (c) 2010-2012, Justin Swanhart
 * with contributions by André Rothe <arothe@phosco.info, phosco@gmx.de>
 *
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *   * Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT
 * SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
 * BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 */
if (!defined('HAVE_SQL_EXPR_PROCESSOR')) {

    require_once(dirname(__FILE__) . '/abstract-processor.php');
    require_once(dirname(__FILE__) . '/from-processor.php');
    require_once(dirname(__FILE__) . '/record-processor.php');
    require_once(dirname(__FILE__) . '/update-processor.php');
    require_once(dirname(__FILE__) . '/delete-processor.php');
    require_once(dirname(__FILE__) . '/group-processor.php');
    require_once(dirname(__FILE__) . '/rename-processor.php');
    require_once(dirname(__FILE__) . '/using-processor.php');
    require_once(dirname(__FILE__) . '/describe-processor.php');
    require_once(dirname(__FILE__) . '/having-processor.php');
    require_once(dirname(__FILE__) . '/replace-processor.php');
    require_once(dirname(__FILE__) . '/values-processor.php');
    require_once(dirname(__FILE__) . '/drop-processor.php');
    require_once(dirname(__FILE__) . '/insert-processor.php');
    require_once(dirname(__FILE__) . '/select-expression-processor.php');
    require_once(dirname(__FILE__) . '/where-processor.php');
    require_once(dirname(__FILE__) . '/duplicate-processor.php');
    require_once(dirname(__FILE__) . '/into-processor.php');
    require_once(dirname(__FILE__) . '/select-processor.php');
    require_once(dirname(__FILE__) . '/explain-processor.php');
    require_once(dirname(__FILE__) . '/limit-processor.php');
    require_once(dirname(__FILE__) . '/set-processor.php');
    require_once(dirname(__FILE__) . '/expression-list-processor.php');
    require_once(dirname(__FILE__) . '/order-processor.php');
    require_once(dirname(__FILE__) . '/show-processor.php');
    require_once(dirname(__FILE__) . '/create-processor.php');
    require_once(dirname(__FILE__) . '/table-processor.php');

    /**
     * 
     * This class processes the SQL chunks.
     * 
     * @author arothe
     * 
     */
    class SQLExpressionProcessor extends AbstractProcessor {

        public function process($out) {
            if (!$out) {
                return false;
            }
            if (!empty($out['CREATE'])) {
                $processor = new CreateProcessor();
                $out['CREATE'] = $processor->process($out['CREATE']);
            }
            if (!empty($out['TABLE'])) {
                $processor = new TableProcessor();
                $out['TABLE'] = $processor->process($out['TABLE']);
                if (isset($out['TABLE']['like'])) {
                    $out = $this->array_insert_after($out, 'TABLE', array('LIKE' => $out['TABLE']['like']));
                    unset($out['TABLE']['like']);
                }
            }
            if (!empty($out['EXPLAIN'])) {
                $processor = new ExplainProcessor();
                $out['EXPLAIN'] = $processor->process($out['EXPLAIN'], isset($out['SELECT']));
            }
            if (!empty($out['DESCRIBE'])) {
                $processor = new DescribeProcessor();
                $out['DESCRIBE'] = $processor->process($out['DESCRIBE']);
            }
            if (!empty($out['SELECT'])) {
                $processor = new SelectProcessor();
                $out['SELECT'] = $processor->process($out['SELECT']);
            }
            if (!empty($out['FROM'])) {
                $processor = new FromProcessor();
                $out['FROM'] = $processor->process($out['FROM']);
            }
            if (!empty($out['USING'])) {
                $processor = new UsingProcessor();
                $out['USING'] = $processor->process($out['USING']);
            }
            if (!empty($out['UPDATE'])) {
                $processor = new UpdateProcessor();
                $out['UPDATE'] = $processor->process($out['UPDATE']);
            }
            if (!empty($out['GROUP'])) {
                // set empty array if we have partial SQL statement
                $processor = new GroupByProcessor();
                $out['GROUP'] = $processor->process($out['GROUP'], isset($out['SELECT']) ? $out['SELECT'] : array());
            }
            if (!empty($out['ORDER'])) {
                // set empty array if we have partial SQL statement
                $processor = new OrderByProcessor();
                $out['ORDER'] = $processor->process($out['ORDER'], isset($out['SELECT']) ? $out['SELECT'] : array());
            }
            if (!empty($out['LIMIT'])) {
                $processor = new LimitProcessor();
                $out['LIMIT'] = $processor->process($out['LIMIT']);
            }
            if (!empty($out['WHERE'])) {
                $processor = new WhereProcessor();
                $out['WHERE'] = $processor->process($out['WHERE']);
            }
            if (!empty($out['HAVING'])) {
                $processor = new HavingProcessor();
                $out['HAVING'] = $processor->process($out['HAVING']);
            }
            if (!empty($out['SET'])) {
                $processor = new SetProcessor();
                $out['SET'] = $processor->process($out['SET'], isset($out['UPDATE']));
            }
            if (!empty($out['DUPLICATE'])) {
                $processor = new DuplicateProcessor();
                $out['ON DUPLICATE KEY UPDATE'] = $processor->process($out['DUPLICATE']);
                unset($out['DUPLICATE']);
            }
            if (!empty($out['INSERT'])) {
                $processor = new InsertProcessor();
                $out = $processor->process($out);
            }
            if (!empty($out['REPLACE'])) {
                $processor = new ReplaceProcessor();
                $out = $processor->process($out);
            }
            if (!empty($out['DELETE'])) {
                $processor = new DeleteProcessor();
                $out = $processor->process($out);
            }
            if (!empty($out['VALUES'])) {
                $processor = new ValuesProcessor();
                $out = $processor->process($out);
            }
            if (!empty($out['INTO'])) {
                $processor = new IntoProcessor();
                $out = $processor->process($out);
            }
            if (!empty($out['DROP'])) {
                $processor = new DropProcessor();
                $out['DROP'] = $processor->process($out['DROP']);
            }
            if (!empty($out['RENAME'])) {
                $processor = new RenameProcessor();
                $out['RENAME'] = $processor->process($out['RENAME']);
            }
            if (!empty($out['SHOW'])) {
                $processor = new ShowProcessor();
                $out['SHOW'] = $processor->process($out['SHOW']);
            }
            return $out;
        }
    }
    define('HAVE_SQL_EXPR_PROCESSOR', 1);
}
