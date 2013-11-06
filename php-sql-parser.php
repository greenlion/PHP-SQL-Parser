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

    require_once(dirname(__FILE__) . '/classes/parser-utils.php');
    require_once(dirname(__FILE__) . '/classes/lexer.php');
    require_once(dirname(__FILE__) . '/classes/position-calculator.php');
    require_once(dirname(__FILE__) . '/classes/processors/abstract-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/from-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/record-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/update-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/delete-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/group-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/rename-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/using-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/describe-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/having-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/replace-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/values-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/drop-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/insert-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/select-expression-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/where-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/duplicate-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/into-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/select-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/explain-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/limit-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/set-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/expression-list-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/order-processor.php');
    require_once(dirname(__FILE__) . '/classes/processors/show-processor.php');

    /**
     * This class implements the parser functionality.
     *
     * @author greenlion@gmail.com
     * @author arothe@phosco.info
     */
    class PHPSQLParser extends PHPSQLParserUtils {

        private $lexer;

        public function __construct($sql = false, $calcPositions = false) {
            $this->lexer = new PHPSQLLexer();
            if ($sql) {
                $this->parse($sql, $calcPositions);
            }
        }

        public function parse($sql, $calcPositions = false) {
            // lex the SQL statement
            $inputArray = $this->splitSQLIntoTokens($sql);

            // this is the highest level lexical analysis. This is the part of the
            // ode which finds UNION and UNION ALL query parts
            $queries = $this->processUnion($inputArray);

            // If there was no UNION or UNION ALL in the query, then the query is
            // stored at $queries[0].
            if (!$this->isUnion($queries)) {
                $queries = $this->processSQL($queries[0]);
            }

            // calc the positions of some important tokens
            if ($calcPositions) {
                $calculator = new PositionCalculator();
                $queries = $calculator->setPositionsWithinSQL($sql, $queries);
            }

            // store the parsed queries
            $this->parsed = $queries;
            return $this->parsed;
        }

        private function processUnion($inputArray) {
            $outputArray = array();

            // ometimes the parser needs to skip ahead until a particular
            // oken is found
            $skipUntilToken = false;

            // his is the last type of union used (UNION or UNION ALL)
            // ndicates a) presence of at least one union in this query
            // b) the type of union if this is the first or last query
            $unionType = false;

            // ometimes a "query" consists of more than one query (like a UNION query)
            // his array holds all the queries
            $queries = array();

            foreach ($inputArray as $key => $token) {
                $trim = trim($token);

                // overread all tokens till that given token
                if ($skipUntilToken) {
                    if ($trim === "") {
                        continue; // read the next token
                    }
                    if (strtoupper($trim) === $skipUntilToken) {
                        $skipUntilToken = false;
                        continue; // read the next token
                    }
                }

                if (strtoupper($trim) !== "UNION") {
                    $outputArray[] = $token; // here we get empty tokens, if we remove these, we get problems in parse_sql()
                    continue;
                }

                $unionType = "UNION";

                // we are looking for an ALL token right after UNION
                for ($i = $key + 1; $i < count($inputArray); ++$i) {
                    if (trim($inputArray[$i]) === "") {
                        continue;
                    }
                    if (strtoupper($inputArray[$i]) !== "ALL") {
                        break;
                    }
                    // the other for-loop should overread till "ALL"
                    $skipUntilToken = "ALL";
                    $unionType = "UNION ALL";
                }

                // store the tokens related to the unionType
                $queries[$unionType][] = $outputArray;
                $outputArray = array();
            }

            // the query tokens after the last UNION or UNION ALL
            // or we don't have an UNION/UNION ALL
            if (!empty($outputArray)) {
                if ($unionType) {
                    $queries[$unionType][] = $outputArray;
                } else {
                    $queries[] = $outputArray;
                }
            }

            return $this->processMySQLUnion($queries);
        }

        /**
         * MySQL supports a special form of UNION:
         * (select ...)
         * union
         * (select ...)
         *
         * This function handles this query syntax. Only one such subquery
         * is supported in each UNION block. (select)(select)union(select) is not legal.
         * The extra queries will be silently ignored.
         */
        private function processMySQLUnion($queries) {
            $unionTypes = array('UNION', 'UNION ALL');
            foreach ($unionTypes as $unionType) {

                if (empty($queries[$unionType])) {
                    continue;
                }

                foreach ($queries[$unionType] as $key => $tokenList) {
                    foreach ($tokenList as $z => $token) {
                        $token = trim($token);
                        if ($token === "") {
                            continue;
                        }

                        // starts with "(select"
                        if (preg_match("/^\\(\\s*select\\s*/i", $token)) {
                            $queries[$unionType][$key] = $this->parse($this->removeParenthesisFromStart($token));
                            break;
                        }

                        $queries[$unionType][$key] = $this->processSQL($queries[$unionType][$key]);
                        break;
                    }
                }
            }
            // it can be parsed or not
            return $queries;
        }

        private function isUnion($queries) {
            $unionTypes = array('UNION', 'UNION ALL');
            foreach ($unionTypes as $unionType) {
                if (!empty($queries[$unionType])) {
                    return true;
                }
            }
            return false;
        }

        // this function splits up a SQL statement into easy to "parse"
        // tokens for the SQL processor
        private function splitSQLIntoTokens($sql) {
            return $this->lexer->split($sql);
        }

        /*
         * This function breaks up the SQL statement into logical sections. Some sections are then further handled by specialized functions.
         */
        private function processSQL(&$tokens) {
            $prev_category = "";
            $token_category = "";
            $skip_next = false;
            $out = false;

            $tokenCount = count($tokens);
            for ($tokenNumber = 0; $tokenNumber < $tokenCount; ++$tokenNumber) {

                $token = $tokens[$tokenNumber];
                $trim = trim($token); // this removes also \n and \t!

                // if it starts with an "(", it should follow a SELECT
                if ($trim !== "" && $trim[0] === "(" && $token_category === "") {
                    $token_category = 'SELECT';
                }

                /*
                 * If it isn't obvious, when $skip_next is set, then we ignore the next real token, that is we ignore whitespace.
                 */
                if ($skip_next) {
                    if ($trim === "") {
                        if ($token_category !== "") { // is this correct??
                            $out[$token_category][] = $token;
                        }
                        continue;
                    }
                    // o skip the token we replace it with whitespace
                    $trim = "";
                    $token = "";
                    $skip_next = false;
                }

                $upper = strtoupper($trim);
                switch ($upper) {

                /* Tokens that get their own sections. These keywords have subclauses. */
                case 'SELECT':
                case 'ORDER':
                case 'SET':
                case 'DUPLICATE':
                case 'VALUES':
                case 'GROUP':
                case 'HAVING':
                case 'WHERE':
                case 'CALL':
                case 'PROCEDURE':
                case 'FUNCTION':
                case 'SERVER':
                case 'LOGFILE':
                case 'DEFINER':
                case 'RETURNS':
                case 'TABLESPACE':
                case 'TRIGGER':
                case 'DO':
                case 'FLUSH':
                case 'KILL':
                case 'RESET':
                case 'STOP':
                case 'PURGE':
                case 'EXECUTE':
                case 'PREPARE':
                case 'DEALLOCATE':
                    if ($trim === 'DEALLOCATE') {
                        $skip_next = true;
                    }
                    $token_category = $upper;
                    break;

                case 'LIMIT':
                case 'PLUGIN':
                // no separate section
                    if ($token_category === 'SHOW') {
                        continue;
                    }
                    $token_category = $upper;
                    break;

                case 'FROM': /* this FROM is different from FROM in other DML (not join related) */
                    if ($token_category === 'PREPARE') {
                        continue 2;
                    }
                    // no separate section
                    if ($token_category === 'SHOW') {
                        continue;
                    }
                    $token_category = $upper;
                    break;

                case 'EXPLAIN':
                case 'DESCRIBE':
                case 'SHOW':
                    $token_category = $upper;
                    break;

                case 'RENAME':
                // jump over TABLE keyword
                    $token_category = $upper;
                    $skip_next = true;
                    continue 2;

                case 'DATABASE':
                case 'SCHEMA':
                    if ($prev_category === 'DROP') {
                        continue;
                    }
                    if ($prev_category === 'SHOW') {
                        continue;
                    }
                    $token_category = $upper;
                    break;

                case 'EVENT':
                // issue 71
                    if ($prev_category === 'DROP' || $prev_category === 'ALTER' || $prev_category === 'CREATE') {
                        $token_category = $upper;
                    }
                    break;

                case 'DATA':
                // prevent wrong handling of DATA as keyword
                    if ($prev_category === 'LOAD') {
                        $token_category = $upper;
                    }
                    break;

                case 'INTO':
                // prevent wrong handling of CACHE within LOAD INDEX INTO CACHE...
                    if ($prev_category === 'LOAD') {
                        $out[$prev_category][] = $upper;
                        continue 2;
                    }
                    $token_category = $upper;
                    break;

                case 'USER':
                // prevent wrong processing as keyword
                    if ($prev_category === 'CREATE' || $prev_category === 'RENAME' || $prev_category === 'DROP') {
                        $token_category = $upper;
                    }
                    break;

                case 'VIEW':
                // prevent wrong processing as keyword
                    if ($prev_category === 'CREATE' || $prev_category === 'ALTER' || $prev_category === 'DROP') {
                        $token_category = $upper;
                    }
                    break;

                /*
                 * These tokens get their own section, but have no subclauses. These tokens identify the statement but have no specific subclauses of their own.
                 */
                case 'DELETE':
                case 'ALTER':
                case 'INSERT':
                case 'REPLACE':
                case 'TRUNCATE':
                case 'OPTIMIZE':
                case 'GRANT':
                case 'REVOKE':
                case 'HANDLER':
                case 'LOAD':
                case 'ROLLBACK':
                case 'SAVEPOINT':
                case 'UNLOCK':
                case 'INSTALL':
                case 'UNINSTALL':
                case 'ANALZYE':
                case 'BACKUP':
                case 'CHECK':
                case 'CHECKSUM':
                case 'REPAIR':
                case 'RESTORE':
                case 'USE':
                case 'HELP':
                    $token_category = $upper;
                    // set the category in case these get subclauses in a future version of MySQL
                    $out[$upper][0] = $upper;
                    continue 2;
                    break;

                case 'CREATE':
                    if ($prev_category === 'SHOW') {
                        continue;
                    }
                    $token_category = $upper;
                    // set the category in case these get subclauses in a future version of MySQL
                    $out[$upper][0] = $upper;
                    continue 2;
                    break;

                case 'CACHE':
                    if ($prev_category === "" || $prev_category === 'RESET' || $prev_category === 'FLUSH'
                            || $prev_category === 'LOAD') {
                        $token_category = $upper;
                        continue 2;
                    }
                    break;

                /* This is either LOCK TABLES or SELECT ... LOCK IN SHARE MODE */
                case 'LOCK':
                    if ($token_category === "") {
                        $token_category = $upper;
                        $out[$upper][0] = $upper;
                    } else {
                        $trim = 'LOCK IN SHARE MODE';
                        $skip_next = true;
                        $out['OPTIONS'][] = $trim;
                    }
                    continue 2;
                    break;

                case 'USING': /* USING in FROM clause is different from USING w/ prepared statement*/
                    if ($token_category === 'EXECUTE') {
                        $token_category = $upper;
                        continue 2;
                    }
                    if ($token_category === 'FROM' && !empty($out['DELETE'])) {
                        $token_category = $upper;
                        continue 2;
                    }
                    break;

                /* DROP TABLE is different from ALTER TABLE DROP ... */
                case 'DROP':
                    if ($token_category !== 'ALTER') {
                        $token_category = $upper;
                        continue 2;
                    }
                    break;

                case 'FOR':
                    if ($prev_category === 'SHOW') {
                        continue;
                    }
                    $skip_next = true;
                    $out['OPTIONS'][] = 'FOR UPDATE';
                    continue 2;
                    break;

                case 'UPDATE':
                    if ($token_category === "") {
                        $token_category = $upper;
                        continue 2;
                    }
                    if ($token_category === 'DUPLICATE') {
                        continue 2;
                    }
                    break;

                case 'START':
                    $trim = "BEGIN";
                    $out[$upper][0] = $upper;
                    $skip_next = true;
                    break;

                /* These tokens are ignored. */
                case 'TO':
                    if ($token_category === 'RENAME') {
                        break;
                    }
                case 'BY':
                case 'ALL':
                case 'SHARE':
                case 'MODE':
                case ';':
                    continue 2;
                    break;

                case 'KEY':
                    if ($token_category === 'DUPLICATE') {
                        continue 2;
                    }
                    break;

                /* These tokens set particular options for the statement. They never stand alone. */
                case 'LOW_PRIORITY':
                case 'DELAYED':
                case 'IGNORE':
                case 'FORCE':
                case 'QUICK':
                    $out['OPTIONS'][] = $upper;
                    continue 2;
                    break;

                case 'WITH':
                    if ($token_category === 'GROUP') {
                        $skip_next = true;
                        $out['OPTIONS'][] = 'WITH ROLLUP';
                        continue 2;
                    }
                    break;

                case 'AS':
                    break;

                case '':
                case ',':
                case ';':
                    break;

                default:
                    break;
                }

                // remove obsolete category after union (empty category because of
                // empty token before select)
                if ($token_category !== "" && ($prev_category === $token_category)) {
                    $out[$token_category][] = $token;
                }

                $prev_category = $token_category;
            }

            return $this->processSQLParts($out);
        }

        private function processSQLParts($out) {
            if (!$out) {
                return false;
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
    define('HAVE_PHP_SQL_PARSER', 1);
}
