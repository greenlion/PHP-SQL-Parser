<?php

/**
 * php-sql-parser.php
 *
 * A pure PHP SQL (non validating) parser w/ focus on MySQL dialect of SQL
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
if (!defined('HAVE_PHP_SQL_PARSER')) {

    require_once(dirname(__FILE__) . '/classes/position-calculator.php');
    require_once(dirname(__FILE__) . '/classes/processors/default-processor.php');

    /**
     * This class implements the parser functionality.
     *
     * @author greenlion@gmail.com
     * @author arothe@phosco.info
     */
    class PHPSQLParser {

        public $parsed;
        
        public function __construct($sql = false, $calcPositions = false) {
            if ($sql) {
                $this->parse($sql, $calcPositions);
            }
        }
        
        public function parse($sql, $calcPositions = false) {
            
            $processor = new DefaultProcessor();
            $queries = $processor->process($sql);
                        
            // calc the positions of some important tokens
            if ($calcPositions) {
                $calculator = new PositionCalculator();
                $queries = $calculator->setPositionsWithinSQL($sql, $queries);
            }

            // store the parsed queries
            $this->parsed = $queries;
            return $this->parsed;
        }
    }
    define('HAVE_PHP_SQL_PARSER', 1);
}
