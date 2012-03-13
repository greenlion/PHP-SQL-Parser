<?php
/**
 * PHPSQLParser
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

    class PHPSQLParser {
        var $reserved = array();
        var $functions = array();

        public function __construct($sql = false, $calcPositions = false) {
            $this->load_reserved_words();
            if ($sql) {
                $this->parse($sql, $calcPositions);
            }
        }

        public function parse($sql, $calcPositions = false) {
            $original = $this->replaceSpecialCharacters($sql);

            #lex the SQL statement
            $inputArray = $this->split_sql(trim($original));

            #This is the highest level lexical analysis.  This is the part of the
            #code which finds UNION and UNION ALL query parts
            $queries = $this->processUnion($inputArray);

            # If there was no UNION or UNION ALL in the query, then the query is
            # stored at $queries[0].
            if (!$this->isUnion($queries)) {
                $queries = $this->process_sql($queries[0]);
            }

            # calc the positions of some important tokens
            if ($calcPositions) {
                $queries = $this->calculatePositionsWithinSQL($original, $queries);
            }

            # store the parsed queries
            $this->parsed = $queries;
            return $this->parsed;
        }

        private function preprint($s, $return = false) {
            $x = "<pre>";
            $x .= print_r($s, 1);
            $x .= "</pre>";
            if ($return) {
                return $x;
            } else {
                if (isset($_ENV['DEBUG'])) {
                    print $x . "\n";
                }
            }
        }

        private function processUnion($inputArray) {
            $outputArray = array();

            #sometimes the parser needs to skip ahead until a particular
            #token is found
            $skipUntilToken = false;

            #This is the last type of union used (UNION or UNION ALL)
            #indicates a) presence of at least one union in this query
            #          b) the type of union if this is the first or last query
            $unionType = false;

            #Sometimes a "query" consists of more than one query (like a UNION query)
            #this array holds all the queries
            $queries = array();

            foreach ($inputArray as $key => $token) {
                $trim = trim($token);

                # overread all tokens till that given token
                if ($skipUntilToken) {
                    if ($trim === "") {
                        continue; # read the next token
                    }
                    if (strtoupper($trim) === $skipUntilToken) {
                        $skipUntilToken = false;
                        continue; # read the next token
                    }
                }

                if (strtoupper($trim) !== "UNION") {
                    $outputArray[] = $token; # here we get empty tokens, if we remove these, we get problems in parse_sql()
                    continue;
                }

                $unionType = 'UNION';

                # we are looking for an ALL token right after UNION
                for ($i = $key + 1; $i < count($inputArray); ++$i) {
                    if (trim($inputArray[$i]) === "") {
                        continue;
                    }
                    if (strtoupper($inputArray[$i]) !== 'ALL') {
                        break;
                    }
                    # the other for-loop should overread till "ALL"
                    $skipUntilToken = 'ALL';
                    $unionType = 'UNION ALL';
                }

                # store the tokens related to the unionType
                $queries[$unionType][] = $outputArray;
                $outputArray = array();
            }

            # the query tokens after the last UNION or UNION ALL
            # or we don't have an UNION/UNION ALL
            if (!empty($outputArray)) {
                if ($unionType) {
                    $queries[$unionType][] = $outputArray;
                } else {
                    $queries[] = $outputArray;
                }
            }

            return $this->processMySQLUnion($queries);
        }

        /** MySQL supports a special form of UNION:
         * (select ...)
         * union
         * (select ...)
         *
         * This function handles this query syntax.  Only one such subquery
         * is supported in each UNION block.  (select)(select)union(select) is not legal.
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

                        # starts with "(select"
                        if (preg_match('/^\\(\\s*select\\s*/i', $token)) {
                            $queries[$unionType][$key] = $this->parse($this->removeParenthesisFromStart($token));
                            break;
                        }

                        $queries[$unionType][$key] = $this->process_sql($queries[$unionType][$key]);
                        break;
                    }
                }
            }
            # it can be parsed or not
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

        private function calculatePositionsWithinSQL($sql, $parsed) {
            $charPos = 0;
            $backtracking = array();
            $this->lookForBaseExpression($sql, $charPos, $parsed, 0, $backtracking);
            return $parsed;
        }

        # helper method to debug lookForBaseExpression
        private function printPos($text, $sql, $charPos, $key, $parsed, $backtracking) {
            if (!isset($_ENV['DEBUG'])) {
                return;
            }

            $spaces = "";
            $caller = debug_backtrace();
            $i = 1;
            while ($caller[$i]['function'] === 'lookForBaseExpression') {
                $spaces .= "   ";
                $i++;
            }
            $holdem = substr($sql, 0, $charPos) . "^" . substr($sql, $charPos);
            echo $spaces . $text . " key:" . $key . "  parsed:" . $parsed . " back:" . serialize($backtracking) . " "
                    . $holdem . "\n";
        }

        private function findPositionWithinString($sql, $value, $expr_type) {

            $allowedOnOperator = array('\t', '\n', '\r', ' ', ',', '(', ')', '_', '\'');
            $allowedOnOthers = array('\t', '\n', '\r', ' ', ',', '(', ')', '<', '>', '*', '+', '-', '/', '|', '&', '=',
                                     '!', ';');

            $offset = 0;
            $ok = false;
            while (true) {

                $pos = strpos($sql, $value, $offset);
                if ($pos === false) {
                    break;
                }

                $before = "";
                if ($pos > 0) {
                    $before = $sql[$pos - 1];
                }

                $after = "";
                if (isset($sql[$pos + strlen($value)])) {
                    $after = $sql[$pos + strlen($value)];
                }

                # if we have an operator, it should be surrounded by
                # whitespace, comma, parenthesis, digit or letter, end_of_string
                # an operator should not be surrounded by another operator

                if ($expr_type === 'operator') {

                    $ok = ($before === "" || in_array($before, $allowedOnOperator))
                            || (strtolower($before) >= 'a' && strtolower($before) <= 'z')
                            || ($before >= '0' && $before <= '9');
                    $ok = $ok
                            && ($after === "" || in_array($after, $allowedOnOperator)
                                    || (strtolower($after) >= 'a' && strtolower($after) <= 'z')
                                    || ($after >= '0' && $after <= '9'));

                    if (!$ok) {
                        $offset = $pos + 1;
                        continue;
                    }

                    break;
                }

                # in all other cases we accept
                # whitespace, comma, operators, parenthesis and end_of_string

                $ok = ($before === "" || in_array($before, $allowedOnOthers));
                $ok = $ok && ($after === "" || in_array($after, $allowedOnOthers));

                if ($ok) {
                    break;
                }

                $offset = $pos + 1;
            }

            return $pos;
        }

        private function lookForBaseExpression($sql, &$charPos, &$parsed, $key, &$backtracking) {
            if (!is_numeric($key)) {
                if (in_array($key, array('UNION', 'UNION ALL'), true)
                        || ($key === 'expr_type' && $parsed === 'expression')
                        || ($key === 'expr_type' && $parsed === 'subquery')
                        || ($key === 'expr_type' && $parsed === 'table_expression')
                        || ($key === 'expr_type' && $parsed === 'record') || ($key === 'alias' && $parsed !== false)) {
                    $backtracking[] = $charPos; # on the next base_expr we set the pointer back to this value

                } elseif (($key === 'ref_clause' || $key === 'columns') && $parsed !== false) {
                    $backtracking[] = $charPos;
                    for ($i = 1; $i < count($parsed); $i++) {
                        $backtracking[] = false; # backtracking only after n base_expr!
                    }
                } else {
                    # SELECT, WHERE, INSERT etc.
                    if (in_array($key, $this->reserved)) {
                        $charPos = stripos($sql, $key, $charPos);
                        $charPos += strlen($key);
                    }
                }
            }

            if (!is_array($parsed)) {
                return;
            }

            foreach ($parsed as $key => $value) {
                if ($key === 'base_expr') {

                    $subject = substr($sql, $charPos);
                    $pos = $this->findPositionWithinString($subject, $value,
                            isset($parsed['expr_type']) ? $parsed['expr_type'] : 'alias');
                    if ($pos === false) {
                        die("cannot calculate position of " . $value . " within " . $subject);
                    }

                    $parsed['position'] = $charPos + $pos;
                    $charPos += $pos + strlen($value);

                    $oldPos = array_pop($backtracking);
                    if (isset($oldPos) && $oldPos !== false) {
                        $charPos = $oldPos;
                    }

                } else {
                    $this->lookForBaseExpression($sql, $charPos, $parsed[$key], $key, $backtracking);
                }
            }

        }

        private function replaceSpecialCharacters($sql) {
            # replace always the same number of characters, so we have the same positions
            # within the original string

            # issue 21: replace tabs within the query string
            # TODO: this removes special characters also from inside of Strings (is that a problem?)
            return str_replace(array("\r\n", "\n", "\t", "()"), array("  ", " ", " ", "  "), $sql);
        }

        #This function counts open and close backticks and
        #returns their location.  This might be faster as a regex
        private function count_backtick($token, $char) {
            $len = strlen($token);
            $cnt = 0;
            $escaped = false;

            for ($i = 0; $i < $len; ++$i) {
                if ($token[$i] === '\\') {
                    $escaped = true;
                    continue;
                }
                if (!$escaped && $token[$i] == $char) {
                    ++$cnt;
                }
                $escaped = false;
            }
            return $cnt;
        }

        # backticks are not balanced within one token, so we have
        # to re-combine some tokens
        private function balanceBackticks($tokens, $char) {
            $token_count = count($tokens);
            $i = 0;
            while ($i < $token_count) {

                if ($tokens[$i] === "") {
                    $i++;
                    continue;
                }

                $needed = $this->count_backtick($tokens[$i], $char) % 2;
                if ($needed === 0) {
                    $i++;
                    continue;
                }

                for ($n = $i + 1; $n < $token_count; $n++) {
                    $needed = ($needed + $this->count_backtick($tokens[$n], $char)) % 2;

                    $tokens[$i] .= $tokens[$n];
                    unset($tokens[$n]);

                    if ($needed === 0) {
                        $n++;
                        break;
                    }
                }
                $i = $n;
            }
            return array_values($tokens);
        }

        #This is the lexer
        #this function splits up a SQL statement into easy to "parse"
        #tokens for the SQL processor
        private function split_sql($sql) {

            if (!is_string($sql)) {
                echo "SQL:\n";
                print_r($sql);
                exit;
            }

            # added some code from issue 11 comment 3
            $regex = <<<EOREGEX
/(`(?:[^`]|``)`|[@A-Za-z0-9_.`-]+)
|(\+|-|\*|\/|!=|>=|<=|<>|>|<|&&|\|\||=|\^|\(|\))
|('(?:[^']+|'')*'+)
|("(?:[^"]+|"")*"+)
|([^ ,]+)
/ix
EOREGEX
            ;

            $tokens = preg_split($regex, $sql, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            $tokens = $this->balanceParenthesis($tokens);
            $tokens = $this->balanceBackticks($tokens, '`');
            $tokens = $this->balanceBackticks($tokens, '\'');
            return $tokens;
        }

        private function balanceParenthesis($tokens) {

            $token_count = count($tokens);

            $i = 0;
            while ($i < $token_count) {

                if ($tokens[$i] !== '(') {
                    $i++;
                    continue;
                }

                $count = 1;
                for ($n = $i + 1; $n < $token_count; $n++) {

                    $token = $tokens[$n];

                    if ($token === '(') {
                        $count++;
                    }
                    if ($token === ')') {
                        $count--;
                    }

                    $tokens[$i] .= $token;
                    unset($tokens[$n]);

                    if ($count === 0) {
                        $n++;
                        break;
                    }
                }
                $i = $n;
            }

            return array_values($tokens);
        }

        /* This function breaks up the SQL statement into logical sections.
         Some sections are then further handled by specialized functions.
         */
        private function process_sql(&$tokens, $start_at = 0, $stop_at = false) {
            $prev_category = "";
            $start = microtime(true);
            $token_category = "";

            $skip_next = false;
            $token_count = count($tokens);

            if (!$stop_at) {
                $stop_at = $token_count;
            }

            $out = false;

            for ($token_number = $start_at; $token_number < $stop_at; ++$token_number) {
                $token = trim($tokens[$token_number]);
                # if it starts with an "(", it should follow a SELECT
                if ($token !== "" && $token[0] == '(' && $token_category == "") {
                    $token_category = 'SELECT';
                }

                /* If it isn't obvious, when $skip_next is set, then we ignore the next real
                 token, that is we ignore whitespace.
                 */
                if ($skip_next) {
                    #whitespace does not count as a next token
                    if ($token === "") {
                        continue;
                    }

                    #to skip the token we replace it with whitespace
                    $token = "";
                    $skip_next = false;
                }

                $upper = strtoupper($token);
                switch ($upper) {

                /* Tokens that get their own sections. These keywords have subclauses. */
                case 'SELECT':
                case 'ORDER':
                case 'LIMIT':
                case 'SET':
                case 'DUPLICATE':
                case 'VALUES':
                case 'GROUP':
                case 'ORDER':
                case 'HAVING':
                case 'WHERE':
                case 'RENAME':
                case 'CALL':
                case 'PROCEDURE':
                case 'FUNCTION':
                case 'DATABASE':
                case 'SERVER':
                case 'LOGFILE':
                case 'DEFINER':
                case 'RETURNS':
                case 'EVENT':
                case 'TABLESPACE':
                case 'TRIGGER':
                case 'DATA':
                case 'DO':
                case 'PASSWORD':
                case 'PLUGIN':
                case 'FROM':
                case 'FLUSH':
                case 'KILL':
                case 'RESET':
                case 'START':
                case 'STOP':
                case 'PURGE':
                case 'EXECUTE':
                case 'PREPARE':
                case 'DEALLOCATE':
                    if ($token == 'DEALLOCATE') {
                        $skip_next = true;
                    }
                    /* this FROM is different from FROM in other DML (not join related) */
                    if ($token_category == 'PREPARE' && $upper == 'FROM') {
                        continue 2;
                    }

                    $token_category = $upper;
                    break;

                case 'INTO':
                # prevent wrong handling of CACHE within LOAD INDEX INTO CACHE...
                    if ($prev_category === 'LOAD') {
                        $out[$prev_category][] = $upper;
                        continue 2;
                    }
                    $token_category = $upper;
                    break;

                case 'USER':
                # prevent wrong processing as keyword
                    if (in_array($prev_category, array('CREATE', 'RENAME', 'DROP'), true)) {
                        $token_category = $upper;
                    }
                    break;

                case 'VIEW':
                # prevent wrong processing as keyword
                    if (in_array($prev_category, array('CREATE', 'ALTER', 'DROP'), true)) {
                        $token_category = $upper;
                    }
                    break;

                /* These tokens get their own section, but have no subclauses.
                 These tokens identify the statement but have no specific subclauses of their own. */
                case 'DELETE':
                case 'ALTER':
                case 'INSERT':
                case 'REPLACE':
                case 'TRUNCATE':
                case 'CREATE':
                case 'TRUNCATE':
                case 'OPTIMIZE':
                case 'GRANT':
                case 'REVOKE':
                case 'SHOW':
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
                case 'DESCRIBE':
                case 'EXPLAIN':
                case 'USE':
                case 'HELP':
                    $token_category = $upper; /* set the category in case these get subclauses
                                              in a future version of MySQL */
                    $out[$upper][0] = $upper;
                    continue 2;
                    break;

                case 'CACHE':
                    if (($prev_category === "") || (in_array($prev_category, array('RESET', 'FLUSH', 'LOAD')))) {
                        $token_category = $upper;
                        continue 2;
                    }
                    break;

                /* This is either LOCK TABLES or SELECT ... LOCK IN SHARE MODE*/
                case 'LOCK':
                    if ($token_category == "") {
                        $token_category = $upper;
                        $out[$upper][0] = $upper;
                    } else {
                        $token = 'LOCK IN SHARE MODE';
                        $skip_next = true;
                        $out['OPTIONS'][] = $token;
                    }
                    continue 2;
                    break;

                case 'USING': /* USING in FROM clause is different from USING w/ prepared statement*/
                    if ($token_category == 'EXECUTE') {
                        $token_category = $upper;
                        continue 2;
                    }
                    if ($token_category == 'FROM' && !empty($out['DELETE'])) {
                        $token_category = $upper;
                        continue 2;
                    }
                    break;

                /* DROP TABLE is different from ALTER TABLE DROP ... */
                case 'DROP':
                    if ($token_category != 'ALTER') {
                        $token_category = $upper;
                        $out[$upper][0] = $upper;
                        continue 2;
                    }
                    break;

                case 'FOR':
                    $skip_next = true;
                    $out['OPTIONS'][] = 'FOR UPDATE';
                    continue 2;
                    break;

                case 'UPDATE':
                    if ($token_category == "") {
                        $token_category = $upper;
                        continue 2;

                    }
                    if ($token_category == 'DUPLICATE') {
                        continue 2;
                    }
                    break;

                case 'START':
                    $token = "BEGIN";
                    $out[$upper][0] = $upper;
                    $skip_next = true;
                    break;

                /* These tokens are ignored. */
                case 'BY':
                case 'ALL':
                case 'SHARE':
                case 'MODE':
                case 'TO':
                case ';':
                    continue 2;
                    break;

                case 'KEY':
                    if ($token_category == 'DUPLICATE') {
                        continue 2;
                    }
                    break;

                /* These tokens set particular options for the statement.  They never stand alone.*/
                case 'DISTINCTROW':
                    $token = 'DISTINCT';
                case 'DISTINCT':
                case 'HIGH_PRIORITY':
                case 'LOW_PRIORITY':
                case 'DELAYED':
                case 'IGNORE':
                case 'FORCE':
                case 'STRAIGHT_JOIN':
                case 'SQL_SMALL_RESULT':
                case 'SQL_BIG_RESULT':
                case 'QUICK':
                case 'SQL_BUFFER_RESULT':
                case 'SQL_CACHE':
                case 'SQL_NO_CACHE':
                case 'SQL_CALC_FOUND_ROWS':
                    $out['OPTIONS'][] = $upper;
                    continue 2;
                    break;

                case 'WITH':
                    if ($token_category == 'GROUP') {
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

                # remove obsolete category after union (empty category because of
                # empty token before select)
                if ($token_category && ($prev_category == $token_category)) {

                    # if we have more than one whitespace within a token
                    # we add a token for every character
                    # this prevents wrong base_expr within process_from()
                    # TODO: may it is better to leave the original whitespace as token
                    if ($token === "" && trim($tokens[$token_number]) === "") {
                        for ($i = 0; $i < strlen($tokens[$token_number]) - 1; $i++) {
                            $out[$token_category][] = $token;
                        }
                    }
                    $out[$token_category][] = $token;
                }

                $prev_category = $token_category;
            }

            if (!$out)
                return false;

            #process the SELECT clause
            if (!empty($out['SELECT'])) {
                $out['SELECT'] = $this->process_select($out['SELECT']);
            }
            if (!empty($out['FROM'])) {
                $out['FROM'] = $this->process_from($out['FROM']);
            }
            if (!empty($out['USING'])) {
                $out['USING'] = $this->process_from($out['USING']);
            }
            if (!empty($out['UPDATE'])) {
                $out['UPDATE'] = $this->process_from($out['UPDATE']);
            }
            if (!empty($out['GROUP'])) {
                $out['GROUP'] = $this->process_group($out['GROUP'], $out['SELECT']);
            }
            if (!empty($out['ORDER'])) {
                $out['ORDER'] = $this->process_group($out['ORDER'], $out['SELECT']);
            }
            if (!empty($out['LIMIT'])) {
                $out['LIMIT'] = $this->process_limit($out['LIMIT']);
            }
            if (!empty($out['WHERE'])) {
                $out['WHERE'] = $this->process_expr_list($out['WHERE']);
            }
            if (!empty($out['HAVING'])) {
                $out['HAVING'] = $this->process_expr_list($out['HAVING']);
            }
            if (!empty($out['SET'])) {
                $out['SET'] = $this->process_set_list($out['SET']);
            }
            if (!empty($out['DUPLICATE'])) {
                $out['ON DUPLICATE KEY UPDATE'] = $this->process_set_list($out['DUPLICATE']);
                unset($out['DUPLICATE']);
            }
            if (!empty($out['INSERT'])) {
                $out = $this->process_insert($out);
            }
            if (!empty($out['REPLACE'])) {
                $out = $this->process_insert($out, 'REPLACE');
            }
            if (!empty($out['DELETE'])) {
                $out = $this->process_delete($out);
            }
            if (!empty($out['VALUES'])) {
                $out = $this->process_values($out);
            }
            return $out;
        }

        /* A SET list is simply a list of key = value expressions separated by comma (,).
         This function produces a list of the key/value expressions.
         */
        private function getColumn($column, $expression, $base_expr) {
            return array('column' => trim($column), 'expr' => trim($expression), 'base_expr' => trim($base_expr));
        }

        private function process_set_list($tokens) {
            $column = "";
            $expression = "";
            $base_expr = "";

            foreach ($tokens as $token) {
                $base_expr .= $token;
                $trim = trim($token);

                if ($column === "") {
                    if ($trim === "")
                        continue;
                    $column .= $trim;
                    continue;
                }

                if ($trim === "=")
                    continue;

                if ($trim === ",") {
                    $expr[] = $this->getColumn($column, $expression, $base_expr);
                    $expression = $column = $base_expr = "";
                    continue;
                }

                $expression .= $token;
            }
            if ($expression) {
                $expr[] = $this->getColumn($column, $expression, $base_expr);
            }

            return $expr;
        }

        /* This function processes the LIMIT section.
         start,end are set.  If only end is provided in the query
         then start is set to 0.
         */
        private function process_limit($tokens) {
            $start = 0;
            $end = 0;

            if ($pos = array_search(',', $tokens)) {
                for ($i = 0; $i < $pos; ++$i) {
                    if ($tokens[$i] !== "") {
                        $start = $tokens[$i];
                        break;
                    }
                }
                $pos = $pos + 1;

            } else {
                $pos = 0;
            }

            for ($i = $pos; $i < count($tokens); ++$i) {
                if ($tokens[$i] !== "") {
                    $end = $tokens[$i];
                    break;
                }
            }

            return array('start' => $start, 'end' => $end);
        }

        /* This function processes the SELECT section.  It splits the clauses at the commas.
         Each clause is then processed by process_select_expr() and the results are added to
         the expression list.
        
         Finally, at the end, the epxression list is returned.
         */
        private function process_select(&$tokens) {
            $expression = "";
            $expr = array();
            foreach ($tokens as $token) {
                if ($token == ',') {
                    $expr[] = $this->process_select_expr(trim($expression));
                    $expression = "";
                } else {
                    if ($token === "") {
                        $token = " ";
                    }
                    $expression .= $token;
                }
            }
            if ($expression) {
                $expr[] = $this->process_select_expr(trim($expression));
            }
            return $expr;
        }

        private function revokeEscaping($sql) {
            $sql = trim($sql);
            if (($sql[0] === '`') && ($sql[strlen($sql) - 1] === '`')) {
                $sql = substr($sql, 1, -1);
            }
            return str_replace('``', '`', $sql);
        }

        /* This fuction processes each SELECT clause.  We determine what (if any) alias
         is provided, and we set the type of expression.
         */
        private function process_select_expr($expression) {

            $tokens = $this->split_sql($expression);
            $token_count = count($tokens);

            /* Determine if there is an explicit alias after the AS clause.
             If AS is found, then the next non-whitespace token is captured as the alias.
             The tokens after (and including) the AS are removed.
             */
            $base_expr = "";
            $stripped = array();
            $capture = false;
            $alias = false;
            $processed = false;
            for ($i = 0; $i < $token_count; ++$i) {
                $token = strtoupper($tokens[$i]);
                if (trim($token) !== "") {
                    $stripped[] = $tokens[$i];
                }

                if ($token == 'AS') {
                    $alias = array('as' => true, "name" => "", "base_expr" => $tokens[$i]);
                    $tokens[$i] = "";
                    array_pop($stripped); // remove it from the expression
                    $capture = true;
                    continue;
                }

                if ($capture) {
                    if (trim($token) !== "") {
                        $alias['name'] .= $tokens[$i];
                        array_pop($stripped);
                    }
                    $alias['base_expr'] .= $tokens[$i];
                    $tokens[$i] = "";
                    continue;
                }
                $base_expr .= $tokens[$i];
            }

            $stripped = $this->process_expr_list($stripped);

            # we remove the last token, if it is a colref,
            # it can be an alias without an AS
            $last = array_pop($stripped);
            if (!$alias && $last['expr_type'] == 'colref') {

                # check the token before the colref
                $prev = array_pop($stripped);

                if (isset($prev)
                        && ($prev['expr_type'] == 'reserved' || $prev['expr_type'] == 'const'
                                || $prev['expr_type'] == 'function' || $prev['expr_type'] == 'expression'
                                || $prev['expr_type'] == 'subquery' || $prev['expr_type'] == 'colref')) {

                    $alias = array('as' => false, 'name' => trim($last['base_expr']),
                                   'base_expr' => trim($last['base_expr']));
                    #remove the last token
                    array_pop($tokens);
                    $base_expr = join("", $tokens);
                }
            }

            if (!$alias) {
                $base_expr = join("", $tokens);
            } else {
                /* remove escape from the alias */
                $alias['name'] = $this->revokeEscaping(trim($alias['name']));
                $alias['base_expr'] = trim($alias['base_expr']);
            }

            # this is always done with $stripped, how we do it twice?
            $processed = $this->process_expr_list($tokens);

            # if there is only one part, we copy the expr_type
            # in all other cases we use "expression" as global type
            $type = 'expression';
            if (count($processed) == 1) {
                if ($processed[0]['expr_type'] != 'subquery') {
                    $type = $processed[0]['expr_type'];
                    $base_expr = $processed[0]['base_expr'];
                    $processed = $processed[0]['sub_tree']; // it can be FALSE
                }
            }

            return array('expr_type' => $type, 'alias' => $alias, 'base_expr' => trim($base_expr),
                         'sub_tree' => $processed);
        }

        private function process_from(&$tokens) {

            $parseInfo = $this->initParseInfoForFrom();
            $expr = array();

            $skip_next = false;
            $i = 0;

            foreach ($tokens as $token) {
                $upper = strtoupper(trim($token));

                if ($skip_next && $token !== "") {
                    $parseInfo['token_count']++;
                    $skip_next = false;
                    continue;
                } else {
                    if ($skip_next) {
                        continue;
                    }
                }

                switch ($upper) {
                case 'OUTER':
                case 'LEFT':
                case 'RIGHT':
                case 'NATURAL':
                case 'CROSS':
                case ',':
                case 'JOIN':
                case 'INNER':
                    break;

                default:
                    $parseInfo['expression'] .= $token == '' ? " " : $token;
                    if ($parseInfo['ref_type'] !== false) { # all after ON / USING
                        $parseInfo['ref_expr'] .= $token == '' ? " " : $token;
                    }
                    break;
                }

                switch ($upper) {
                case 'AS':
                    $parseInfo['alias'] = array('as' => true, 'name' => "", 'base_expr' => $token);
                    $parseInfo['token_count']++;
                    $n = 1;
                    $str = "";
                    while ($str == "") {
                        $parseInfo['alias']['base_expr'] .= ($tokens[$i + $n] === "" ? " " : $tokens[$i + $n]);
                        $str = trim($tokens[$i + $n]);
                        ++$n;
                    }
                    $parseInfo['alias']['name'] = $str;
                    $parseInfo['alias']['base_expr'] = trim($parseInfo['alias']['base_expr']);
                    continue;

                case 'INDEX':
                    if ($token_category == 'CREATE') {
                        $token_category = $upper;
                        continue 2;
                    }

                    break;

                case 'USING':
                case 'ON':
                    $parseInfo['ref_type'] = $upper;
                    $parseInfo['ref_expr'] = "";

                case 'CROSS':
                case 'USE':
                case 'FORCE':
                case 'IGNORE':
                case 'INNER':
                case 'OUTER':
                    $parseInfo['token_count']++;
                    continue;
                    break;

                case 'FOR':
                    $parseInfo['token_count']++;
                    $skip_next = true;
                    continue;
                    break;

                case 'LEFT':
                case 'RIGHT':
                case 'STRAIGHT_JOIN':
                    $parseInfo['next_join_type'] = $upper;
                    break;

                case ',':
                    $parseInfo['next_join_type'] = 'CROSS';

                case 'JOIN':
                    if ($parseInfo['subquery']) {
                        $parseInfo['sub_tree'] = $this->parse($this->removeParenthesisFromStart($parseInfo['subquery']));
                        $parseInfo['expression'] = $parseInfo['subquery'];
                    }

                    $expr[] = $this->processFromExpression($parseInfo);
                    $parseInfo = $this->initParseInfoForFrom($parseInfo);
                    break;

                default:
                    if ($upper === "") {
                        continue; # ends the switch statement!
                    }

                    if ($parseInfo['token_count'] == 0) {
                        if ($parseInfo['table'] === "") {
                            $parseInfo['table'] = $token;
                        }
                    } else if ($parseInfo['token_count'] == 1) {
                        $parseInfo['alias'] = array('as' => false, 'name' => trim($token), 'base_expr' => trim($token));
                    }
                    $parseInfo['token_count']++;
                    break;
                }
                ++$i;
            }

            $expr[] = $this->processFromExpression($parseInfo);
            return $expr;
        }

        private function initParseInfoForFrom($parseInfo = false) {
            # first init
            if ($parseInfo === false) {
                $parseInfo = array('join_type' => "", 'saved_join_type' => "JOIN");
            }
            # loop init
            return array('expression' => "", 'token_count' => 0, 'table' => "", 'alias' => false, 'join_type' => "",
                         'next_join_type' => "", 'saved_join_type' => $parseInfo['saved_join_type'],
                         'ref_type' => false, 'ref_expr' => false, 'base_expr' => false, 'sub_tree' => false,
                         'subquery' => "");
        }

        private function processFromExpression(&$parseInfo) {

            $res = array();

            # exchange the join types (join_type is save now, saved_join_type holds the next one)
            $parseInfo['join_type'] = $parseInfo['saved_join_type']; # initialized with JOIN
            $parseInfo['saved_join_type'] = ($parseInfo['next_join_type'] ? $parseInfo['next_join_type'] : 'JOIN');

            # we have a reg_expr, so we have to parse it
            if ($parseInfo['ref_expr'] !== false) {
                $parseInfo['ref_expr'] = $this->process_expr_list(
                        $this->split_sql($this->removeParenthesisFromStart($parseInfo['ref_expr'])));
            }

            # there is an expression, we have to parse it
            if (substr(trim($parseInfo['table']), 0, 1) == '(') {
                $parseInfo['expression'] = $this->removeParenthesisFromStart($parseInfo['table']);

                if (preg_match("/^\\s*select/i", $parseInfo['expression'])) {
                    $parseInfo['sub_tree'] = $this->parse($parseInfo['expression']);
                    $res['expr_type'] = 'subquery';
                } else {
                    $tmp = $this->split_sql($parseInfo['expression']);
                    $parseInfo['sub_tree'] = $this->process_from($tmp);
                    $res['expr_type'] = 'table_expression';
                }
            } else {
                $res['expr_type'] = 'table';
                $res['table'] = $parseInfo['table'];
            }

            $res['alias'] = $parseInfo['alias'];
            $res['join_type'] = $parseInfo['join_type'];
            $res['ref_type'] = $parseInfo['ref_type'];
            $res['ref_clause'] = $parseInfo['ref_expr'];
            $res['base_expr'] = trim($parseInfo['expression']);
            $res['sub_tree'] = $parseInfo['sub_tree'];
            return $res;
        }

        private function processGroupExpression(&$parseInfo, $select) {
            if ($parseInfo['expr'] === "") {
                return false;
            }

            $parseInfo['expr'] = trim($this->revokeEscaping($parseInfo['expr']));

            if (is_numeric($parseInfo['expr'])) {
                $parseInfo['type'] = 'pos';
            } else {
                #search to see if the expression matches an alias
                foreach ($select as $clause) {
                    if (!$clause['alias']) {
                        continue;
                    }
                    if ($clause['alias']['name'] == $parseInfo['expr']) {
                        $parseInfo['type'] = 'alias';
                    }
                }

                if (!$parseInfo['type']) {
                    $parseInfo['type'] = "expression";
                }
            }

            return array('type' => $parseInfo['type'], 'base_expr' => $parseInfo['expr'],
                         'direction' => $parseInfo['dir']);
        }

        private function initParseInfoForGroup() {
            return array('expr' => "", 'dir' => "ASC", 'type' => 'expression');
        }

        private function process_group(&$tokens, &$select) {
            $out = array();
            $parseInfo = $this->initParseInfoForGroup();

            if (!$tokens) {
                return false;
            }

            foreach ($tokens as $token) {
                switch (strtoupper($token)) {
                case ',':
                    $out[] = $this->processGroupExpression($parseInfo, $select);
                    $parseInfo = $this->initParseInfoForGroup();
                    break;

                case 'DESC':
                    $parseInfo['dir'] = "DESC";
                    break;

                case 'ASC':
                    $parseInfo['dir'] = "ASC";
                    break;

                default:
                    $parseInfo['expr'] .= $token == '' ? ' ' : $token;

                }
            }

            $out[] = $this->processGroupExpression($parseInfo, $select);
            return $out;
        }

        private function removeParenthesisFromStart($token) {

            $parenthesisRemoved = 0;

            $trim = trim($token);
            if ($trim !== "" && $trim[0] === "(") { // remove only one parenthesis pair now!
                $parenthesisRemoved++;
                $trim[0] = " ";
                $trim = trim($trim);
            }

            $parenthesis = $parenthesisRemoved;
            $i = 0;
            $string = 0;
            while ($i < strlen($trim)) {

                if ($trim[$i] === '\\') {
                    $i += 2; # an escape character, the next character is irrelevant
                    continue;
                }

                if (in_array($trim[$i], array('\'', '"'))) {
                    $string++;
                }

                if (($string % 2 === 0) && ($trim[$i] === "(")) {
                    $parenthesis++;
                }

                if (($string % 2 === 0) && ($trim[$i] === ")")) {
                    if ($parenthesis == $parenthesisRemoved) {
                        $trim[$i] = " ";
                        $parenthesisRemoved--;
                    }
                    $parenthesis--;
                }
                $i++;
            }
            return trim($trim);
        }

        /* Some sections are just lists of expressions, like the WHERE and HAVING clauses.  This function
         processes these sections.  Recursive.
         */
        private function process_expr_list($tokens) {

            $processed = false;
            $expr = "";
            $expr_type = false;
            $type = "";
            $prev_token = "";
            $prev_token_type = "";
            $skip_next = false;
            $sub_expr = "";

            $in_lists = array();
            foreach ($tokens as $key => $token) {

                if (trim($token) === "") {
                    continue;
                }
                if ($skip_next) {
                    $skip_next = false;
                    continue;
                }

                $processed = false;
                $upper = strtoupper(trim($token));

                if (trim($token) !== "") {
                    $token = trim($token);
                }

                /* is it a subquery?*/
                if (preg_match("/^\\s*\\(\\s*SELECT/i", $token)) {
                    $type = 'subquery';
                    #tokenize and parse the subquery.
                    #we remove the enclosing parenthesis for the tokenizer
                    $processed = $this->parse($this->removeParenthesisFromStart($token));

                    /* is it an inlist */
                } elseif ($upper[0] == '(' && substr($upper, -1) == ')') {

                    # if we have a colref followed by a parenthesis pair,
                    # it isn't a colref, it is a user-function
                    if (in_array($prev_token_type, array('colref', 'function', 'aggregate_function'))) {
                        $tmptokens = $this->split_sql($this->removeParenthesisFromStart($token));

                        foreach ($tmptokens as $k => $v) {
                            if (trim($v) == ',') { # TODO: check tabs or newlines next to the comma
                                unset($tmptokens[$k]);
                            }
                        }
                        $processed = $this->process_expr_list($tmptokens);

                        $prev_token = "";
                        $prev_token_type = "";

                        $last = array_pop($expr);
                        $token = $last['base_expr'];
                        $type = 'function';
                    }

                    if ($prev_token == 'IN') {
                        $type = "in-list";
                        $tmptokens = $this->split_sql($this->removeParenthesisFromStart($token));
                        foreach ($tmptokens as $k => $v) {
                            if (trim($v) == ',') {
                                unset($tmptokens[$k]);
                            }
                        }
                        $processed = $this->process_expr_list($tmptokens);

                        $prev_token = "";
                        $prev_token_type = "";
                    }

                    if ($prev_token == 'AGAINST') {
                        $type = "match-arguments";
                        $list = $this->split_sql($this->removeParenthesisFromStart($token));
                        if (count($list) > 1) {
                            $match_mode = implode('', array_slice($list, 1));
                            $processed = array($list[0], $match_mode);
                        } else {
                            $processed = $list[0];
                        }
                        $prev_token = "";
                        $prev_token_type = "";
                    }

                    /* it is either an operator, a colref or a constant */
                } else {
                    switch ($upper) {

                    case '*':
                        $processed = false; #no subtree

                        # last token is colref, const or expression
                        # it is an operator, in all other cases it is an all-columns-alias
                        # if the previous colref ends with a dot, the * is the all-columns-alias
                        if (!is_array($expr)) {
                            $type = "colref"; # single element *
                            break;
                        }

                        $last = array_pop($expr);
                        if (!in_array($last['expr_type'], array('colref', 'const', 'expression'))) {
                            $expr[] = $last;
                            $type = "colref";
                            break;
                        }

                        if ($last['expr_type'] === 'colref' && substr($last['base_expr'], -1, 1) === ".") {
                            $last['base_expr'] .= '*'; # tablealias dot *
                            $expr[] = $last;
                            continue 2;
                        }

                        $expr[] = $last;
                        $type = "operator";
                        break;

                    case 'AND':
                    case '&&':
                    case 'BETWEEN':
                    case 'AND':
                    case 'BINARY':
                    case '&':
                    case '~':
                    case '|':
                    case '^':
                    case 'DIV':
                    case '/':
                    case '<=>':
                    case '=':
                    case '>=':
                    case '>':
                    case 'IS':
                    case 'NOT':
                    case 'NULL':
                    case '<<':
                    case '<=':
                    case '<':
                    case 'LIKE':
                    case '-':
                    case '%':
                    case '!=':
                    case '<>':
                    case 'REGEXP':
                    case '!':
                    case '||':
                    case 'OR':
                    case '+':
                    case '>>':
                    case 'RLIKE':
                    case 'SOUNDS':
                    case '-':
                    case 'XOR':
                    case 'IN':
                        $processed = false;
                        $type = "operator";
                        break;

                    default:
                        switch ($token[0]) {
                        case "'":
                        case '"':
                            $type = 'const';
                            break;
                        case '`':
                            $type = 'colref';
                            break;

                        default:
                            if (is_numeric($token)) {
                                $type = 'const';
                            } else {
                                $type = 'colref';
                            }
                            break;

                        }
                        $processed = false;
                    }
                }

                /* is a reserved word? */
                if (($type != 'operator' && $type != 'in-list' && $type != 'sub_expr' && $type != 'function')
                        && in_array($upper, $this->reserved)) {
                    if (!in_array($upper, $this->functions)) {
                        $type = 'reserved';
                    } else {
                        switch ($upper) {
                        case 'AVG':
                        case 'SUM':
                        case 'COUNT':
                        case 'MIN':
                        case 'MAX':
                        case 'STDDEV':
                        case 'STDDEV_SAMP':
                        case 'STDDEV_POP':
                        case 'VARIANCE':
                        case 'VAR_SAMP':
                        case 'VAR_POP':
                        case 'GROUP_CONCAT':
                        case 'BIT_AND':
                        case 'BIT_OR':
                        case 'BIT_XOR':
                            $type = 'aggregate_function';
                            if (isset($tokens[$key + 1]) && $tokens[$key + 1] !== "") {
                                $sub_expr = $tokens[$key + 1];
                            }
                            break;

                        default:
                            $type = 'function';
                            if (isset($tokens[$key + 1]) && $tokens[$key + 1] !== "") {
                                $sub_expr = $tokens[$key + 1];
                            } else {
                                $sub_expr = "()";
                            }
                            break;
                        }
                    }
                }

                if (!$type) {
                    if ($upper[0] == '(') {
                        $local_expr = $this->removeParenthesisFromStart($token);
                    } else {
                        $local_expr = $token;
                    }
                    $processed = $this->process_expr_list($this->split_sql($local_expr));
                    $type = 'expression';

                    if (count($processed) == 1) {
                        $type = $processed[0]['expr_type'];
                        $base_expr = $processed[0]['base_expr'];
                        $processed = $processed[0]['sub_tree'];
                    }

                }

                // TODO: here we loose the parameters of the reserved keywords (like aggregate functions)?
                $sub_expr = "";

                $expr[] = array('expr_type' => $type, 'base_expr' => $token, 'sub_tree' => $processed);

                $prev_token = $upper;
                $prev_token_type = $type;

                $expr_type = "";
                $type = "";
            } // end of for-loop

            if ($sub_expr !== "") {
                $processed['sub_tree'] = $this->process_expr_list(
                        $this->split_sql($this->removeParenthesisFromStart($sub_expr)));
            }

            if (!is_array($processed)) {
                # fixed issue 12.1
                # it seems that we will reach that point if the $tokens is empty
                $this->preprint($processed); # TODO: why we do that?
                $processed = false;
            }

            if ($expr_type) {
                $expr[] = array('expr_type' => $type, 'base_expr' => $token, 'sub_tree' => $processed);
            }
            return $expr;
        }

        private function process_update($tokens) {

        }

        private function process_delete($tokens) {
            $tables = array();
            $del = $tokens['DELETE'];

            foreach ($tokens['DELETE'] as $expression) {
                if ($expression != 'DELETE' && trim($expression, ' .*') != "" && $expression != ',') {
                    $tables[] = trim($expression, '.* ');
                }
            }

            if (empty($tables)) {
                foreach ($tokens['FROM'] as $table) {
                    $tables[] = $table['table'];
                }
            }

            $tokens['DELETE'] = array('TABLES' => $tables);
            return $tokens;
        }

        private function process_insert($tokens, $token_category = 'INSERT') {
            $table = "";
            $cols = array();

            $into = $tokens['INTO'];
            foreach ($into as $token) {
                if (trim($token) === "")
                    continue;
                if ($table === "") {
                    $table = $token;
                } elseif (empty($cols)) {
                    $cols[] = $token;
                }
            }

            if (empty($cols)) {
                $cols = false;
            } else {
                $columns = explode(",", $this->removeParenthesisFromStart($cols[0]));
                $cols = array();
                foreach ($columns as $k => $v) {
                    $cols[] = array('expr_type' => 'colref', 'base_expr' => trim($v));
                }
            }

            unset($tokens['INTO']);
            $tokens[$token_category] = array('table' => $table, 'columns' => $cols, 'base_expr' => $table);
            return $tokens;
        }

        private function process_record($unparsed) {

            $unparsed = $this->removeParenthesisFromStart($unparsed);
            $values = $this->split_sql($unparsed);

            foreach ($values as $k => $v) {
                if (trim($v) === ",") {
                    $values[$k] = "";
                }
            }
            return $this->process_expr_list($values);
        }

        private function process_values($tokens) {

            $unparsed = "";
            foreach ($tokens['VALUES'] as $k => $v) {
                if (trim($v) === "") {
                    continue;
                }
                $unparsed .= $v;
            }

            $values = $this->split_sql($unparsed);

            $parsed = array();
            foreach ($values as $k => $v) {
                if (trim($v) === ",") {
                    unset($values[$k]);
                } else {
                    $values[$k] = array('expr_type' => 'record', 'base_expr' => $v, 'data' => $this->process_record($v));
                }
            }

            $tokens['VALUES'] = array_values($values);
            return $tokens;
        }

        private function load_reserved_words() {

            $this->functions = array('abs', 'acos', 'adddate', 'addtime', 'aes_encrypt', 'aes_decrypt', 'against',
                                     'ascii', 'asin', 'atan', 'avg', 'benchmark', 'bin', 'bit_and', 'bit_or',
                                     'bitcount', 'bitlength', 'cast', 'ceiling', 'char', 'char_length',
                                     'character_length', 'charset', 'coalesce', 'coercibility', 'collation',
                                     'compress', 'concat', 'concat_ws', 'conection_id', 'conv', 'convert',
                                     'convert_tz', 'cos', 'cot', 'count', 'crc32', 'curdate', 'current_user',
                                     'currval', 'curtime', 'database', 'date_add', 'date_diff', 'date_format',
                                     'date_sub', 'day', 'dayname', 'dayofmonth', 'dayofweek', 'dayofyear', 'decode',
                                     'default', 'degrees', 'des_decrypt', 'des_encrypt', 'elt', 'encode', 'encrypt',
                                     'exp', 'export_set', 'extract', 'field', 'find_in_set', 'floor', 'format',
                                     'found_rows', 'from_days', 'from_unixtime', 'get_format', 'get_lock',
                                     'group_concat', 'greatest', 'hex', 'hour', 'if', 'ifnull', 'in', 'inet_aton',
                                     'inet_ntoa', 'insert', 'instr', 'interval', 'is_free_lock', 'is_used_lock',
                                     'last_day', 'last_insert_id', 'lcase', 'least', 'left', 'length', 'ln',
                                     'load_file', 'localtime', 'localtimestamp', 'locate', 'log', 'log2', 'log10',
                                     'lower', 'lpad', 'ltrim', 'make_set', 'makedate', 'maketime', 'master_pos_wait',
                                     'match', 'max', 'md5', 'microsecond', 'mid', 'min', 'minute', 'mod', 'month',
                                     'monthname', 'nextval', 'now', 'nullif', 'oct', 'octet_length', 'old_password',
                                     'ord', 'password', 'period_add', 'period_diff', 'pi', 'position', 'pow', 'power',
                                     'quarter', 'quote', 'radians', 'rand', 'release_lock', 'repeat', 'replace',
                                     'reverse', 'right', 'round', 'row_count', 'rpad', 'rtrim', 'sec_to_time',
                                     'second', 'session_user', 'sha', 'sha1', 'sign', 'soundex', 'space', 'sqrt',
                                     'std', 'stddev', 'stddev_pop', 'stddev_samp', 'strcmp', 'str_to_date', 'subdate',
                                     'substring', 'substring_index', 'subtime', 'sum', 'sysdate', 'system_user', 'tan',
                                     'time', 'timediff', 'timestamp', 'timestampadd', 'timestampdiff', 'time_format',
                                     'time_to_sec', 'to_days', 'trim', 'truncate', 'ucase', 'uncompress',
                                     'uncompressed_length', 'unhex', 'unix_timestamp', 'upper', 'user', 'utc_date',
                                     'utc_time', 'utc_timestamp', 'uuid', 'var_pop', 'var_samp', 'variance', 'version',
                                     'week', 'weekday', 'weekofyear', 'year', 'yearweek');

            /* includes functions */
            $this->reserved = array('abs', 'acos', 'adddate', 'addtime', 'aes_encrypt', 'aes_decrypt', 'against',
                                    'ascii', 'asin', 'atan', 'avg', 'benchmark', 'bin', 'bit_and', 'bit_or',
                                    'bitcount', 'bitlength', 'cast', 'ceiling', 'char', 'char_length',
                                    'character_length', 'charset', 'coalesce', 'coercibility', 'collation', 'compress',
                                    'concat', 'concat_ws', 'conection_id', 'conv', 'convert', 'convert_tz', 'cos',
                                    'cot', 'count', 'crc32', 'curdate', 'current_user', 'currval', 'curtime',
                                    'database', 'date_add', 'date_diff', 'date_format', 'date_sub', 'day', 'dayname',
                                    'dayofmonth', 'dayofweek', 'dayofyear', 'decode', 'default', 'degrees',
                                    'des_decrypt', 'des_encrypt', 'elt', 'encode', 'encrypt', 'exp', 'export_set',
                                    'extract', 'field', 'find_in_set', 'floor', 'format', 'found_rows', 'from_days',
                                    'from_unixtime', 'get_format', 'get_lock', 'group_concat', 'greatest', 'hex',
                                    'hour', 'if', 'ifnull', 'in', 'inet_aton', 'inet_ntoa', 'insert', 'instr',
                                    'interval', 'is_free_lock', 'is_used_lock', 'last_day', 'last_insert_id', 'lcase',
                                    'least', 'left', 'length', 'ln', 'load_file', 'localtime', 'localtimestamp',
                                    'locate', 'log', 'log2', 'log10', 'lower', 'lpad', 'ltrim', 'make_set', 'makedate',
                                    'maketime', 'master_pos_wait', 'match', 'max', 'md5', 'microsecond', 'mid', 'min',
                                    'minute', 'mod', 'month', 'monthname', 'nextval', 'now', 'nullif', 'oct',
                                    'octet_length', 'old_password', 'ord', 'password', 'period_add', 'period_diff',
                                    'pi', 'position', 'pow', 'power', 'quarter', 'quote', 'radians', 'rand',
                                    'release_lock', 'repeat', 'replace', 'reverse', 'right', 'round', 'row_count',
                                    'rpad', 'rtrim', 'sec_to_time', 'second', 'session_user', 'sha', 'sha1', 'sign',
                                    'soundex', 'space', 'sqrt', 'std', 'stddev', 'stddev_pop', 'stddev_samp', 'strcmp',
                                    'str_to_date', 'subdate', 'substring', 'substring_index', 'subtime', 'sum',
                                    'sysdate', 'system_user', 'tan', 'time', 'timediff', 'timestamp', 'timestampadd',
                                    'timestampdiff', 'time_format', 'time_to_sec', 'to_days', 'trim', 'truncate',
                                    'ucase', 'uncompress', 'uncompressed_length', 'unhex', 'unix_timestamp', 'upper',
                                    'user', 'utc_date', 'utc_time', 'utc_timestamp', 'uuid', 'var_pop', 'var_samp',
                                    'variance', 'version', 'week', 'weekday', 'weekofyear', 'year', 'yearweek', 'add',
                                    'all', 'alter', 'analyze', 'and', 'as', 'asc', 'asensitive', 'auto_increment',
                                    'bdb', 'before', 'berkeleydb', 'between', 'bigint', 'binary', 'blob', 'both', 'by',
                                    'call', 'cascade', 'case', 'change', 'char', 'character', 'check', 'collate',
                                    'column', 'columns', 'condition', 'connection', 'constraint', 'continue', 'create',
                                    'cross', 'current_date', 'current_time', 'current_timestamp', 'cursor', 'database',
                                    'databases', 'day_hour', 'day_microsecond', 'day_minute', 'day_second', 'dec',
                                    'decimal', 'declare', 'default', 'delayed', 'delete', 'desc', 'describe',
                                    'deterministic', 'distinct', 'distinctrow', 'div', 'double', 'drop', 'else',
                                    'elseif', 'end', 'enclosed', 'escaped', 'exists', 'exit', 'explain', 'false',
                                    'fetch', 'fields', 'float', 'for', 'force', 'foreign', 'found', 'frac_second',
                                    'from', 'fulltext', 'grant', 'group', 'having', 'high_priority',
                                    'hour_microsecond', 'hour_minute', 'hour_second', 'if', 'ignore', 'in', 'index',
                                    'infile', 'inner', 'innodb', 'inout', 'insensitive', 'insert', 'int', 'integer',
                                    'interval', 'into', 'io_thread', 'is', 'iterate', 'join', 'key', 'keys', 'kill',
                                    'leading', 'leave', 'left', 'like', 'limit', 'lines', 'load', 'localtime',
                                    'localtimestamp', 'lock', 'long', 'longblob', 'longtext', 'loop', 'low_priority',
                                    'master_server_id', 'match', 'mediumblob', 'mediumint', 'mediumtext', 'middleint',
                                    'minute_microsecond', 'minute_second', 'mod', 'natural', 'not',
                                    'no_write_to_binlog', 'null', 'numeric', 'on', 'optimize', 'option', 'optionally',
                                    'or', 'order', 'out', 'outer', 'outfile', 'precision', 'primary', 'privileges',
                                    'procedure', 'purge', 'read', 'real', 'references', 'regexp', 'rename', 'repeat',
                                    'replace', 'require', 'restrict', 'return', 'revoke', 'right', 'rlike',
                                    'second_microsecond', 'select', 'sensitive', 'separator', 'set', 'show',
                                    'smallint', 'some', 'soname', 'spatial', 'specific', 'sql', 'sqlexception',
                                    'sqlstate', 'sqlwarning', 'sql_big_result', 'sql_calc_found_rows',
                                    'sql_small_result', 'sql_tsi_day', 'sql_tsi_frac_second', 'sql_tsi_hour',
                                    'sql_tsi_minute', 'sql_tsi_month', 'sql_tsi_quarter', 'sql_tsi_second',
                                    'sql_tsi_week', 'sql_tsi_year', 'ssl', 'starting', 'straight_join', 'striped',
                                    'table', 'tables', 'terminated', 'then', 'timestampadd', 'timestampdiff',
                                    'tinyblob', 'tinyint', 'tinytext', 'to', 'trailing', 'true', 'undo', 'union',
                                    'unique', 'unlock', 'unsigned', 'update', 'usage', 'use', 'user_resources',
                                    'using', 'utc_date', 'utc_time', 'utc_timestamp', 'values', 'varbinary', 'varchar',
                                    'varcharacter', 'varying', 'when', 'where', 'while', 'with', 'write', 'xor',
                                    'year_month', 'zerofill');

            for ($i = 0; $i < count($this->reserved); ++$i) {
                $this->reserved[$i] = strtoupper($this->reserved[$i]);
                // the funcions should not contain 0
                if (!empty($this->functions[$i])) {
                    $this->functions[$i] = strtoupper($this->functions[$i]);
                }
            }
        }

    } // END CLASS
    define('HAVE_PHP_SQL_PARSER', 1);
}

