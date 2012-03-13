<?php
error_reporting(E_ALL);

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
                        || ($key === 'alias' && $parsed !== false)) {
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

        #This function counts open and close parenthesis and
        #returns their location.  This might be faster as a regex
        private function count_paren($token, $chars = array('(', ')')) {
            $len = strlen($token);
            $open = array();
            $close = array();
            for ($i = 0; $i < $len; ++$i) {
                if ($token[$i] == $chars[0]) {
                    $open[] = $i;
                } elseif ($token[$i] == $chars[1]) {
                    $close[] = $i;
                }

            }
            return array('open' => $open, 'close' => $close, 'balanced' => (count($close) - count($open)));
        }

        #This function counts open and close backticks and
        #returns their location.  This might be faster as a regex
        private function count_backtick($token) {
            $len = strlen($token);
            $cnt = 0;
            for ($i = 0; $i < $len; ++$i) {
                if ($token[$i] == '`')
                    ++$cnt;
            }
            return $cnt;
        }

        private function replaceSpecialCharacters($sql) {
            # replace always the same number of characters, so we have the same positions
            # within the original string

            # issue 21: replace tabs within the query string
            # TODO: this removes special characters also from inside of Strings (is that a problem?)
            return str_replace(array('\\\'', '\\"', "\r\n", "\n", "\t", "()"), array("''", '""', "  ", " ", " ", "  "),
                    $sql);
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
/(`(?:[^`]|``)`|[@A-Za-z0-9_.`-]+(?:\(\s*\)){0,1})
|(\+|-|\*|\/|!=|>=|<=|<>|>|<|&&|\|\||=|\^)
|(\(.*?\))   # Match FUNCTION(...) OR BAREWORDS
|('(?:[^']+|'')*'+)
|("(?:[^"]+|"")*"+)
|([^ ,]+)
/ix
EOREGEX
            ;
            $tokens = preg_split($regex, $sql, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

            $tokens = $this->balanceParenthesis($tokens);
            $tokens = $this->balanceBackticks($tokens);

            return $tokens;
        }

        private function balanceParenthesis($tokens) {
            /* The above regex has one problem, because the parenthetical match is not greedy.
             Thus, when matching grouped expresions such as ( (a and b) or c) the
             tokenizer will produce "( (a and b)", " ", "or", " " , "c,")"
            
             This block detects the number of open/close parens in the given token.  If the parens are balanced
             (balanced == 0) then we don't need to do anything.
            
             otherwise, we need to balance the expression.
             */
            $token_count = count($tokens);
            $reset = false;
            for ($i = 0; $i < $token_count; ++$i) {

                if ($tokens[$i] === "")
                    continue;

                $token = $tokens[$i];

                $trim = trim($token);
                if ($trim !== "") {
                    if ($trim[0] != '(' && substr($trim, -1) == ')') {
                        $trim = trim(substr($trim, 0, strpos($trim, '(')));
                    }
                    $tokens[$i] = $trim;
                    $token = $trim;
                }

                if ($token !== "" && $token[0] == '(') {
                    $info = $this->count_paren($token);
                    if ($info['balanced'] == 0) {
                        continue;
                    }

                    #we need to find this many closing parens
                    $needed = abs($info['balanced']);
                    $n = $i;
                    while ($needed > 0 && $n < $token_count - 1) {
                        ++$n;
                        $token2 = $tokens[$n];
                        $info2 = $this->count_paren($token2);
                        $closes = count($info2['close']);
                        if ($closes != $needed) {
                            $tokens[$i] .= $tokens[$n];
                            $tokens[$n] = "";
                            $reset = true;
                            $info2 = $this->count_paren($tokens[$i]);
                            $needed = abs($info2['balanced']);
                        } else {
                            /*get the string pos of the last close paren we need*/
                            $pos = $info2['close'][count($info2['close']) - 1];
                            $str1 = $str2 = "";
                            if ($pos == 0) {
                                $str1 = ')';
                            } else {
                                $str1 = substr($tokens[$n], 0, $pos) . ')';
                                $str2 = substr($tokens[$n], $pos + 1);
                            }
                            if (strlen($str2) > 0) {
                                $tokens[$n] = $str2;
                            } else {
                                $tokens[$n] = "";
                                $reset = true;
                            }
                            $tokens[$i] .= $str1;
                            $info2 = $this->count_paren($tokens[$i]);
                            $needed = abs($info2['balanced']);

                        }
                    }
                }
            }
            return array_values($tokens);
        }

        private function balanceBackticks($tokens) {
            #the same problem appears with backticks :(

            $token_count = count($tokens);
            for ($i = 0; $i < $token_count; ++$i) {
                if ($tokens[$i] === "") {
                    continue;
                }
                $token = $tokens[$i];
                $needed = true;
                $reset = false;
                if ($needed && $token !== "" && strpos($token, '`') !== false) {
                    $info = $this->count_backtick($token);
                    if ($info % 2 == 0) { #even number of backticks means we are balanced
                        continue;
                    }
                    $needed = 1;

                    $n = $i;
                    while ($needed && $n < $token_count - 1) {
                        $reset = true;
                        ++$n;
                        $token .= $tokens[$n];
                        $tokens[$n] = "";
                        $needed = $this->count_backtick($token) % 2;
                    }
                }
                if ($reset) {
                    $tokens[$i] = $token;
                }
            }
            /* reset the array if we deleted any tokens above */
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
                case 'INTO':
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
                case 'VIEW':
                case 'TRIGGER':
                case 'DATA':
                case 'DO':
                case 'PASSWORD':
                case 'USER':
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
                case 'CACHE':
                case 'DESCRIBE':
                case 'EXPLAIN':
                case 'USE':
                case 'HELP':
                    $token_category = $upper; /* set the category in case these get subclauses
                                              in a future version of MySQL */
                    $out[$upper][0] = $upper;
                    continue 2;
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
                /* Properly escape the alias if it is not escaped */
                $alias['name'] = trim($alias['name']);
                $alias['base_expr'] = trim($alias['base_expr']);
                if ($alias['name'][0] != '`') {
                    $alias['name'] = '`' . str_replace('`', '``', $alias['name']) . '`';
                }
            }

            $processed = false;
            $type = 'expression';

            # this is always done with $stripped, how we do it twice?
            $processed = $this->process_expr_list($tokens);

            # if there is only one part, we copy the expr_type
            # in all other cases we use "expression" as global type
            if (count($processed) == 1) {
                if ($processed[0]['expr_type'] != 'subquery') {
                    $type = $processed[0]['expr_type'];
                    $processed = false;
                }
            }

            return array('expr_type' => $type, 'alias' => $alias, 'base_expr' => trim($base_expr),
                         'sub_tree' => $processed);
        }

        private function process_from(&$tokens) {

            $data = $this->initializeTableExpression();
            $expr = array();

            $skip_next = false;
            $i = 0;

            foreach ($tokens as $token) {
                $upper = strtoupper(trim($token));

                if ($skip_next && $token !== "") {
                    $data['token_count']++;
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
                    $data['expression'] .= $token == '' ? " " : $token;
                    if ($data['ref_type'] !== false) { # all after ON / USING
                        $data['ref_expr'] .= $token == '' ? " " : $token;
                    }
                    break;
                }

                switch ($upper) {
                case 'AS':
                    $data['alias'] = array('as' => true, 'name' => "", 'base_expr' => $token);
                    $data['token_count']++;
                    $n = 1;
                    $str = "";
                    while ($str == "") {
                        $data['alias']['base_expr'] .= ($tokens[$i + $n] === "" ? " " : $tokens[$i + $n]);
                        $str = trim($tokens[$i + $n]);
                        ++$n;
                    }
                    $data['alias']['name'] = $str;
                    $data['alias']['base_expr'] = trim($data['alias']['base_expr']);
                    continue;

                case 'INDEX':
                    if ($token_category == 'CREATE') {
                        $token_category = $upper;
                        continue 2;
                    }

                    break;

                case 'USING':
                case 'ON':
                    $data['ref_type'] = $upper;
                    $data['ref_expr'] = "";

                case 'CROSS':
                case 'USE':
                case 'FORCE':
                case 'IGNORE':
                case 'INNER':
                case 'OUTER':
                    $data['token_count']++;
                    continue;
                    break;

                case 'FOR':
                    $data['token_count']++;
                    $skip_next = true;
                    continue;
                    break;

                case 'LEFT':
                case 'RIGHT':
                case 'STRAIGHT_JOIN':
                    $data['next_join_type'] = $upper;
                    break;

                case ',':
                    $data['next_join_type'] = 'CROSS';

                case 'JOIN':
                    if ($data['subquery']) {
                        $data['sub_tree'] = $this->parse($this->removeParenthesisFromStart($data['subquery']));
                        $data['expression'] = $data['subquery'];
                    }

                    $expr[] = $this->saveTableExpression($data);
                    $data = $this->initializeTableExpression($data);
                    break;

                default:
                    if ($upper === "") {
                        continue; # ends the switch statement!
                    }

                    if ($data['token_count'] == 0) {
                        if ($data['table'] === "") {
                            $data['table'] = $token;
                        }
                    } else if ($data['token_count'] == 1) {
                        $data['alias'] = array('as' => false, 'name' => trim($token), 'base_expr' => trim($token));
                    }
                    $data['token_count']++;
                    break;
                }
                ++$i;
            }

            $expr[] = $this->saveTableExpression($data);
            return $expr;
        }

        private function initializeTableExpression($data = false) {
            # first init
            if ($data === false) {
                $data = array('join_type' => "", 'saved_join_type' => "JOIN");
            }
            # loop init
            return array('expression' => "", 'token_count' => 0, 'table' => "", 'alias' => false, 'join_type' => "",
                         'next_join_type' => "", 'saved_join_type' => $data['saved_join_type'], 'ref_type' => false,
                         'ref_expr' => false, 'base_expr' => false, 'sub_tree' => false, 'subquery' => "");
        }

        private function saveTableExpression(&$data) {

            $res = array();

            # exchange the join types (join_type is save now, saved_join_type holds the next one)
            $data['join_type'] = $data['saved_join_type']; # initialized with JOIN
            $data['saved_join_type'] = ($data['next_join_type'] ? $data['next_join_type'] : 'JOIN');

            # we have a reg_expr, so we have to parse it
            if ($data['ref_expr'] !== false) {
                $data['ref_expr'] = $this->process_expr_list(
                        $this->split_sql($this->removeParenthesisFromStart($data['ref_expr'])));
            }

            # there is an expression, we have to parse it
            if (substr(trim($data['table']), 0, 1) == '(') {
                $data['expression'] = $this->removeParenthesisFromStart($data['table']);
                
                if (preg_match("/^\\s*select/i", $data['expression'])) {
                    $data['sub_tree'] = $this->parse($data['expression']);
                    $res['expr_type'] = 'subquery';
                } else {
                    $tmp = $this->split_sql($data['expression']);
                    $data['sub_tree'] = $this->process_from($tmp);
                    $res['expr_type'] = 'table_expression';
                }
            } else {
                $res['expr_type'] = 'table';
                $res['table'] = $data['table'];
            }

            $res['alias'] = $data['alias'];
            $res['join_type'] = $data['join_type'];
            $res['ref_type'] = $data['ref_type'];
            $res['ref_clause'] = $data['ref_expr'];
            $res['base_expr'] = trim($data['expression']);
            $res['sub_tree'] = $data['sub_tree'];
            return $res;
        }

        private function process_group(&$tokens, &$select) {

            $out = array();
            $expression = "";
            $direction = "ASC";
            $type = "expression";
            if (!$tokens)
                return false;

            foreach ($tokens as $token) {
                switch (strtoupper($token)) {
                case ',':
                    $expression = trim($expression);
                    if ($expression[0] != '`' || substr($expression, -1) != '`') {
                        $escaped = str_replace('`', '``', $expression);
                    } else {
                        $escaped = $expression;
                    }
                    $escaped = '`' . $escaped . '`';

                    if (is_numeric(trim($expression))) {
                        $type = 'pos';
                    } else {

                        // TODO: check the aliass, it is now an array!!

                        #search to see if the expression matches an alias
                        foreach ($select as $clause) {
                            if (!$clause['alias']) {
                                continue;
                            }
                            if ($clause['alias']['name'] == $escaped) {
                                $type = 'alias';
                            }
                        }

                        if (!$type) {
                            $type = "expression";
                        }
                    }

                    $out[] = array('type' => $type, 'base_expr' => $expression, 'direction' => $direction);
                    $escaped = "";
                    $expression = "";
                    $direction = "ASC";
                    $type = "";
                    break;

                case 'DESC':
                    $direction = "DESC";
                    break;

                default:
                    $expression .= $token == '' ? ' ' : $token;

                }
            }
            if ($expression) {
                $expression = trim($expression);
                if ($expression[0] != '`' || substr($expression, -1) != '`') {
                    $escaped = str_replace('`', '``', $expression);
                } else {
                    $escaped = $expression;
                }
                $escaped = '`' . $escaped . '`';

                if (is_numeric(trim($expression))) {
                    $type = 'pos';
                } else {

                    #search to see if the expression matches an alias
                    if (!$type && $select) {
                        foreach ($select as $clause) {
                            if (!is_array($clause))
                                continue;
                            if (!$clause['alias']) {
                                continue;
                            }
                            if ($clause['alias']['name'] == $escaped) {
                                $type = 'alias';
                            }
                        }
                    } else {
                        $type = "expression";
                    }

                    if (!$type)
                        $type = "expression";
                }

                $out[] = array('type' => $type, 'base_expr' => $expression, 'direction' => $direction);
            }

            return $out;
        }

        private function removeParenthesisFromStart($token) {

            $parenthesisRemoved = 0;

            $trim = trim($token);
            while ($trim !== "" && $trim[0] === "(") {
                $parenthesisRemoved++;
                $trim[0] = " ";
                $trim = trim($trim);
            }

            $parenthesis = $parenthesisRemoved;
            for ($i = 0; $i < strlen($trim); $i++) {
                if ($trim[$i] === "(") {
                    $parenthesis++;
                }
                if ($trim[$i] === ")") {
                    if ($parenthesis == $parenthesisRemoved) {
                        $trim[$i] = " ";
                        $parenthesisRemoved--;
                    }
                    $parenthesis--;
                }
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
                    if ($prev_token_type === 'colref') {
                        $tmptokens = $this->split_sql($this->removeParenthesisFromStart($token));

                        foreach ($tmptokens as $k => $v) {
                            if ($v == ',') {
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
                        $processed = $this->split_sql($this->removeParenthesisFromStart($token));
                        $list = array();
                        foreach ($processed as $v) {
                            if ($v == ',')
                                continue;
                            $list[] = $v;
                        }
                        $processed = $list;
                        unset($list);
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
                    $token = $upper;
                    if (!in_array($upper, $this->functions)) {
                        $type = 'reserved';
                    } else {
                        switch ($token) {
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

        private function process_values($tokens) {

            $unparsed = "";
            foreach ($tokens['VALUES'] as $k => $v) {
                if (trim($v) === "") {
                    continue;
                }
                $unparsed .= $v;
            }

            $unparsed = $this->removeParenthesisFromStart($unparsed);
            $values = $this->split_sql($unparsed);

            foreach ($values as $k => $v) {
                if (trim($v) === ",") {
                    $values[$k] = "";
                }
            }
            $tokens['VALUES'] = $this->process_expr_list($values);
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

