<?php
/**
 * PHPSQLLexer.php
 *
 * This file contains the lexer, which splits the SQL statement just before parsing.
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

require_once dirname(__FILE__) . '/../utils/PHPSQLParserUtils.php';
require_once dirname(__FILE__) . '/LexerSplitter.php';
require_once dirname(__FILE__) . '/../exceptions/InvalidParameterException.php';

/**
 * This class splits the SQL string into little parts, which the parser can
 * use to build the result array.
 * 
 * @author arothe
 *
 */
class PHPSQLLexer {

    private $_splitters;

    public function __construct() {
        $this->_splitters = new LexerSplitter();
    }

    /**
     * Ends the given string $haystack with the string $needle?
     * @param string $haystack
     * @param string $needle
     */
    protected function endsWith($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        return (substr($haystack, -$length) === $needle);
    }

    public function split($sql) {
        if (!is_string($sql)) {
            throw new InvalidParameterException($sql);
        }

        $tokens = array();
        $token = "";

        $splitLen = $this->_splitters->getMaxLengthOfSplitter();
        $found = false;
        $len = strlen($sql);
        $pos = 0;

        while ($pos < $len) {

            for ($i = $splitLen; $i > 0; $i--) {
                $substr = substr($sql, $pos, $i);
                if ($this->_splitters->isSplitter($substr)) {

                    if ($token !== "") {
                        $tokens[] = $token;
                    }

                    $tokens[] = $substr;
                    $pos += $i;
                    $token = "";

                    continue 2;
                }
            }

            $token .= $sql[$pos];
            $pos++;
        }

        if ($token !== "") {
            $tokens[] = $token;
        }

        $tokens = $this->concatEscapeSequences($tokens);
        $tokens = $this->balanceBackticks($tokens);
        $tokens = $this->concatColReferences($tokens);
        $tokens = $this->balanceParenthesis($tokens);
        $tokens = $this->concatComments($tokens);
        $tokens = $this->concatUserDefinedVariables($tokens);
        return $tokens;
    }

    private function concatUserDefinedVariables($tokens) {
        $i = 0;
        $cnt = count($tokens);
        $userdef = false;

        while ($i < $cnt) {

            if (!isset($tokens[$i])) {
                $i++;
                continue;
            }

            $token = $tokens[$i];

            if ($userdef !== false) {
                $tokens[$userdef] .= $token;
                unset($tokens[$i]);
                if ($token !== "@") {
                    $userdef = false;
                }
            }

            if ($userdef === false && $token === "@") {
                $userdef = $i;
            }

            $i++;
        }

        return array_values($tokens);
    }

    private function concatComments($tokens) {

        $i = 0;
        $cnt = count($tokens);
        $comment = false;

        while ($i < $cnt) {

            if (!isset($tokens[$i])) {
                $i++;
                continue;
            }

            $token = $tokens[$i];

            if ($comment !== false) {
                if ($inline === true && ($token === "\n" || $token === "\r\n")) {
                    $comment = false;
                } else {
                    unset($tokens[$i]);
                    $tokens[$comment] .= $token;
                }
                if ($inline === false && ($token === "*/")) {
                    $comment = false;
                }
            }

            if (($comment === false) && ($token === "--")) {
                $comment = $i;
                $inline = true;
            }

            if (($comment === false) && ($token === "/*")) {
                $comment = $i;
                $inline = false;
            }

            $i++;
        }

        return array_values($tokens);
    }

    private function isBacktick($token) {
        return ($token === "'" || $token === "\"" || $token === "`");
    }

    private function balanceBackticks($tokens) {
        $i = 0;
        $cnt = count($tokens);
        while ($i < $cnt) {

            if (!isset($tokens[$i])) {
                $i++;
                continue;
            }

            $token = $tokens[$i];

            if ($this->isBacktick($token)) {
                $tokens = $this->balanceCharacter($tokens, $i, $token);
            }

            $i++;
        }

        return $tokens;
    }

    # backticks are not balanced within one token, so we have
    # to re-combine some tokens
    private function balanceCharacter($tokens, $idx, $char) {

        $token_count = count($tokens);
        $i = $idx + 1;
        while ($i < $token_count) {

            if (!isset($tokens[$i])) {
                $i++;
                continue;
            }

            $token = $tokens[$i];
            $tokens[$idx] .= $token;
            unset($tokens[$i]);

            if ($token === $char) {
                break;
            }

            $i++;
        }
        return array_values($tokens);
    }

    /*
     * does the token ends with dot?
     * concat it with the next token
     * 
     * does the token starts with a dot?
     * concat it with the previous token
     */
    private function concatColReferences($tokens) {

        $cnt = count($tokens);
        $i = 0;
        while ($i < $cnt) {

            if (!isset($tokens[$i])) {
                $i++;
                continue;
            }

            if ($tokens[$i][0] === ".") {

                // concat the previous tokens, till the token has been changed
                $k = $i - 1;
                $len = strlen($tokens[$i]);
                while (($k >= 0) && ($len == strlen($tokens[$i]))) {
                    if (!isset($tokens[$k])) { # FIXME: this can be wrong if we have schema . table . column
                        $k--;
                        continue;
                    }
                    $tokens[$i] = $tokens[$k] . $tokens[$i];
                    unset($tokens[$k]);
                    $k--;
                }
            }

            if ($this->endsWith($tokens[$i], '.') && !is_numeric($tokens[$i])) {

                // concat the next tokens, till the token has been changed
                $k = $i + 1;
                $len = strlen($tokens[$i]);
                while (($k < $cnt) && ($len == strlen($tokens[$i]))) {
                    if (!isset($tokens[$k])) {
                        $k++;
                        continue;
                    }
                    $tokens[$i] .= $tokens[$k];
                    unset($tokens[$k]);
                    $k++;
                }
            }

            $i++;
        }

        return array_values($tokens);
    }

    private function concatEscapeSequences($tokens) {
        $tokenCount = count($tokens);
        $i = 0;
        while ($i < $tokenCount) {

            if ($this->endsWith($tokens[$i], "\\")) {
                $i++;
                if (isset($tokens[$i])) {
                    $tokens[$i - 1] .= $tokens[$i];
                    unset($tokens[$i]);
                }
            }
            $i++;
        }
        return array_values($tokens);
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
}

?>
