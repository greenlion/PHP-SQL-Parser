<?php
/**
 * OracleSQLTranslator
 * 
 * A translator from MySQL dialect into Oracle dialect for Limesurvey 
 * (http://www.limesurvey.org/)
 * 
 * Copyright (c) 2012, André Rothe <arothe@phosco.info>
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
require_once('php-sql-parser.php');
require_once('php-sql-creator.php');

class OracleSQLTranslator extends PHPSQLCreator {

   var $con;
   var $preventColumnRefs = false;

   public function __construct($con) {
      parent::__construct();
      $this->con = $con;
   }

   private function preprint($s, $return = false) {
      $x = "<pre>";
      $x .= print_r($s, 1);
      $x .= "</pre>";
      if ($return)
      return $x;
      else
      print $x;
   }

   protected function processAlias($parsed) {
      if ($parsed === false) {
         return "";
      }
      # we don't need an AS between expression and alias
      $sql = " " . $parsed['name'];
      return $sql;
   }
   
   protected function processDELETE($parsed) {
      if (count($parsed('TABLES')) > 1) {
         die("cannot translate delete statement into Oracle dialect, multiple tables are not allowed.");
      }
      return "DELETE";
   }

   private function getColumnNameFor($column) {
      if (strtolower($column) === 'uid') {
         $column = "uid_";
      }
      return $column;
   }

   private function getShortTableNameFor($table) {
      if (strtolower($table) === 'surveys_languagesettings') {
         $table = 'surveys_lngsettings';
      }
      return $table;
   }

   protected function processTable($parsed, $index) {
      if ($parsed['expr_type'] !== 'table') {
         return "";
      }

      $sql = $table = $this->getShortTableNameFor($parsed['table']);
      $sql .= " " . $this->processAlias($parsed['alias']);

      if ($index !== 0) {
         $sql = " " . $this->processJoin($parsed['join_type']) . " " . $sql;
         $sql .= $this->processRefType($parsed['ref_type']);
         $sql .= $this->processRefClause($parsed['ref_clause']);
      }
      return $sql;
   }

   protected function processColRef($parsed) {
      global $preventColumnRefs;

      if ($parsed['expr_type'] !== 'colref') {
         return "";
      }

      $colref = $parsed['base_expr'];
      $pos = strpos($colref, ".");
      if ($pos === false) {
         $pos = -1;
      }
      $table = trim(substr($colref, 0, $pos + 1), ".");
      $col = substr($colref, $pos + 1);

      # we have to change the column name, if the column is uid
      $col = $this->getColumnNameFor($col);

      # we have to change the tablereference, if the tablename is too long
      $table = $this->getShortTableNameFor($table);

      # if we have * as colref, we cannot use other columns
      $preventColumnRefs = $preventColumnRefs || (($table === "") && ($col === "*"));

      $alias = "";
      if (isset($parsed['alias'])) {
         $alias = $this->processAlias($parsed['alias']);
      }

      return (($table !== "") ? ($table . "." . $col) : $col) . $alias;
   }

   protected function processSELECT($parsed) {
      global $preventColumnRefs;

      $sql = parent::processSELECT($parsed);
      if ($preventColumnRefs) {
         $sql = "SELECT *";
         $preventColumnRefs = false;
      }
      return $sql;
   }

   public function process($sql) {
      $parser = new PHPSQLParser($sql);
      print_r($parser->parsed);
      $sql = $this->create($parser->parsed);

      echo $sql . "\n";
      return $sql;
   }
}

/*
 * $sql = substr($sql, 0, $start) . "cast(substr(" . $columnInfo
 . ",1,200) as varchar2(200))"
 . substr($sql, $start + strlen($columnInfo));
 */

$parser = new OracleSQLTranslator(false);

$sql = "SELECT qid FROM QUESTIONS WHERE gid='1' and language='de-informal' ORDER BY question_order, title ASC";
$parser->process($sql);
