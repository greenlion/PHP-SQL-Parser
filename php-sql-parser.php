<?php
error_reporting(E_ALL);

class PHPSQLParser {
	var $reserved = array();
	function __construct($sql = false) {
		#LOAD THE LIST OF RESERVED WORDS
		$this->load_reserved_words();
		if($sql) $this->parse($sql);
	}

	function parse($sql) {
		$sql = trim($sql);
		
		$in = $this->split_sql($sql);

		$out = array();
		$skip_until = false;
		$union = false;
		$queries=array();

		foreach($in as $key => $token) {
			$token=trim($token);
			
			if($skip_until) {
				if(trim($token)) {
					if(strtoupper($token) == $skip_until) {
						$skip_until = false;
						continue;
					}
				} else { 
					continue;
				}
			}

			if(trim(strtoupper($token)) == "UNION") {
				$union = 'UNION';
				for($i=$key+1;$i<count($in);++$i) {
					if(trim($in[$i]) == '') continue;
					if(strtoupper($in[$i]) == 'ALL')  {
						$skip_until = 'ALL';
						$union = 'UNION ALL';
						continue ;
					} else {
						break;
					}
				}

				$queries[$union][] = $out;
				$out = array();
				
			} else { 
				
				if ($token != 'ALL') $out[]=$token;
			}

		}

		if(!empty($out)) {
			if ($union) {
				$queries[$union][] = $out;
			} else {
				$queries[] = $out;
			}
		}
		

		/*MySQL supports a special form of UNION:
		(select ...)
		union
		(select ...)

		This block finds any subqueries such as the following in a UNION statment.  Only one such subquery
		is supported in each UNION block (select)union(select)union(select) is legal, but
		(select)(select)union(select) is not.  The extra queries will be silently ignored.
		*/
		$union_types = array('UNION','UNION ALL');
		foreach($union_types as $union_type) {
			if(!empty($queries[$union_type])) {
				foreach($queries[$union_type] as $i => $tok_list) { 
					foreach($tok_list as $z => $tok) {
						$tok = trim($tok);
						if(!$tok) continue;
						if(preg_match('/^\\(\\s*select\\s*/i', $tok)) {
							$queries[$union_type][$i] = $this->parse(substr($tok,1,-1));
							break;
						} else {
							$queries[$union_type][$i] = $this->process_sql($queries[$union_type][$i]);
							break;
						}
					}
				}
			}
		}


		if(!empty($queries[0])) {
			$queries[0] = $this->process_sql($queries[0]);
			
		}

		if(count($queries) == 1 && !$union) {
			$queries = $queries[0];
		}

		$this->parsed = $queries;
		return $this->parsed;
	}

	private function count_paren($token) {
		$len = strlen($token);
		$open=array();
		$close=array();
		for($i=0;$i<$len;++$i){
			if($token[$i] == '(') {
				$open[] = $i;
			} elseif($token[$i] == ')') {
				$close[] = $i;
			}
			
		}
		return array('open' => $open, 'close' => $close, 'balanced' =>( count($close) - count($open)));
	}

	#this function splits up a SQL statement into easy to "parse"
	#tokens for the SQL processor
	private function split_sql($sql) {

			if(!is_string($sql)) {
				echo "SQL:\n";
				print_r($sql);
				exit;
			}

			$sql = str_replace(array('\\\'','\\"'),array("''",'""'), $sql);
                        $regex=<<<EOREGEX
/([@A-Za-z0-9._*]+)
|(\+|-|\*|\/|!=|&&|\|\||=|\^)
|(\(.*?\))   # Match FUNCTION(...) OR BAREWORDS
|('(?:[^']|'')*'+)
|("(?:[^"]|"")*"+)
|(`(?:[^`]|``)*`+)
|([^ ,]+)
/ix
EOREGEX
;


	        $tokens = preg_split($regex, $sql,-1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		#print_r($tokens);

		$token_count = count($tokens);

		#we need to properly fix-up grouped expressions and subqueries
		#if the parens are balanced (balanced == 0) then we don't need to do anything
		#otherwise, we need to balance the expression.
		for($i=0;$i<$token_count;++$i) {
			if(empty($tokens[$i])) continue;
			$trim = trim($tokens[$i]);
			if($trim) $tokens[$i] = $trim;

			$token=$trim;
			if($token && $token[0] == '(') {
				#echo "TOKEN:$token\n";
				
				$info = $this->count_paren($token);
				if($info['balanced'] == 0) {
					continue;
				}

				#we need to find this many closing parens
				$needed = abs($info['balanced']);
				$n = $i;
				while($needed > 0 && $n <$token_count-1) {
					++$n;
					#echo "LOOKING FORWARD TO $n [ " . $tokens[$n] . "]\n";
					$token2 = $tokens[$n];
					$info2 = $this->count_paren($token2);
					$closes = count($info2['close']);
					if($closes != $needed) {
						$tokens[$i] .= $tokens[$n];
						unset($tokens[$n]);
						$info2 = $this->count_paren($tokens[$i]);
						$needed = abs($info2['balanced']);
					#	echo "CLOSES LESS THAN NEEDED (still need $needed)\n";
					} else {
						/*get the string pos of the last close paren we need*/
						$pos = $info2['close'][count($info2['close'])-1];
						$str1 = $str2 = "";
						if($pos == 0) { 
							$str1 = ')';
						} else {
							$str1 = substr($tokens[$n],0,$pos) . ')';
							$str2 = substr($tokens[$n],$pos+1);
						}
						#echo "CLOSES FOUND AT $n, offset:$pos  [$str1] [$str2]\n";
						if(strlen($str2) > 0) { 
							$tokens[$n] = $str2;
						} else {
							unset($tokens[$n]);
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
	
	/* This function breaks up the SQL statement into logical sections.
	   Some sections are then further handled by specialized functions.
	*/
	private function process_sql(&$tokens,$start_at = 0, $stop_at = false) {
		$prev_category = "";
		$start = microtime(true);
		$token_category = "";
	
		$skip_next=false;
		$token_count = count($tokens);
	
		if(!$stop_at) {
			$stop_at = $token_count;
		}

		$out = false;
	
		for($token_number = $start_at;$token_number<$stop_at;++$token_number) {
			$token = trim($tokens[$token_number]);
			if($token && $token[0] == '(' && $token_category == "") {
				$token_category = 'SELECT';
			}
	
	                /* If it isn't obvious, when $skip_next is set, then we ignore the next real
			token, that is we ignore whitespace.  
	                */
			if($skip_next) {
				#whitespace does not count as a next token
				if($token == "") {
					continue;
				}
			
	                        #to skip the token we replace it with whitespace	
				$new_token = "";
				$skip_next = false;
			}
	
			$upper = strtoupper($token);
			switch($upper) {
	
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
					if($token == 'DEALLOCATE') {
						$skip_next = true;
					}
					/* this FROM is different from FROM in other DML (not join related) */
					if($token_category == 'PREPARE' && $upper == 'FROM') {
						continue 2;
					} 
					
					$token_category = $upper;
					$join_type = 'JOIN';
					if($upper == 'FROM' && $token_category == 'FROM') {
						/* DO NOTHING*/
					} else {
						continue 2;

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
					if($token_category == "") {
						$token_category = $upper; 
						$out[$upper][0] = $upper; 
					} else {
						$token = 'LOCK IN SHARE MODE';
						$skip_next=true;
						$out['OPTIONS'][] = $token;
					}
					continue 2;
				break;
	
				case 'USING':
					/* USING in FROM clause is different from USING w/ prepared statement*/
					if($token_category == 'EXECUTE') {
						$token_category=$upper;
						continue 2;
					}  
					if($token_category == 'FROM' && !empty($out['DELETE'])) {
						$token_category=$upper;
						continue 2;
					}  
				break;
					
				/* DROP TABLE is different from ALTER TABLE DROP ... */
				case 'DROP':		
					if($token_category != 'ALTER') {
						$token_category = $upper; 
						$out[$upper][0] = $upper;
						continue 2;
					}
				break;
	
				case 'FOR':
					$skip_next=true;
					$out['OPTIONS'][] = 'FOR UPDATE';
					continue 2;
				break;
	
	
				case 'UPDATE':
					if($token_category == "" ) {
						$token_category = $upper;
						continue 2;
						
					}
					if($token_category == 'DUPLICATE') {
						continue 2;
					}
					break;
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
				case 'ON':
				case 'TO':
					
				case ';':
					continue 2;
					break;
	
				case 'KEY':
					if($token_category == 'DUPLICATE') {
						continue 2;
					}
				break; 
	
				/* These tokens set particular options for the statement.  They never stand alone.*/
				case 'DISTINCTROW': 
					$token='DISTINCT';
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
					if($token_category == 'GROUP') {
						$skip_next=true;
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
	
			#echo "HERE: $token $token_number\n";	
			if($prev_category == $token_category) { 
				$out[$token_category][] = $token;
			}
	
			$prev_category = $token_category;
		}

		if(!$out) return false;

	
		#process the SELECT clause
		if(!empty($out['SELECT'])) $out['SELECT'] = $this->process_select($out['SELECT']);	

		if(!empty($out['FROM']))   $out['FROM'] = $this->process_from($out['FROM']);	
		if(!empty($out['USING']))   $out['USING'] = $this->process_from($out['USING']);	
		if(!empty($out['UPDATE']))  $out['UPDATE'] = $this->process_from($out['UPDATE']);

		if(!empty($out['GROUP']))  $out['GROUP'] = $this->process_group($out['GROUP'], $out['SELECT']);	
		if(!empty($out['ORDER']))  $out['ORDER'] = $this->process_group($out['ORDER'], $out['SELECT']);	

		if(!empty($out['LIMIT']))  $out['LIMIT'] = $this->process_limit($out['LIMIT']);

		if(!empty($out['WHERE']))  $out['WHERE'] = $this->process_expr_list($out['WHERE']);
		if(!empty($out['HAVING']))  $out['HAVING'] = $this->process_expr_list($out['HAVING']);
		if(!empty($out['SET']))  $out['SET'] = $this->process_set_list($out['SET']);
		if(!empty($out['DUPLICATE'])) {
			$out['ON DUPLICATE KEY UPDATE'] = $this->process_set_list($out['DUPLICATE']);
			unset($out['DUPLICATE']);
		}
		if(!empty($out['INSERT']))  $out = $this->process_insert($out);
		if(!empty($out['REPLACE']))  $out = $this->process_insert($out,'REPLACE');
		if(!empty($out['DELETE']))  $out = $this->process_delete($out);
	
		return $out;
	
	}

	/* A SET list is simply a list of key = value expressions separated by comma (,).
	This function produces a list of the key/value expressions.
	*/
	private function process_set_list($tokens) {
		$column="";
		$expression="";
		foreach($tokens as $token) {
			$token=trim($token);
			if(!$column) { 
				if(!$token) continue;
				$column .= $token;
				continue;
			}

			if($token == '=') continue;

			if($token == ',') {
				$expr[] = array('column' => trim($column), 'expr' => trim($expression));
				$expression = $column = "";
				continue;
			}	

			$expression .= $token;
		}
		if($expression) {
			$expr[] = array('column' => trim($column), 'expr' => trim($expression));
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
	
		if($pos = array_search(',',$tokens)) {
			for($i=0;$i<$pos;++$i) {
				if($tokens[$i] != '') {
					$start = $tokens[$i];
					break;
				}
			}
			$pos = $pos + 1;
	
		} else {
			$pos = 0;
		}
	
		for($i=$pos;$i<count($tokens);++$i) {
			if($tokens[$i] != '') {
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
		foreach($tokens as $token) {
			if($token == ',') {
				$expr[] = $this->process_select_expr(trim($expression));
				$expression = "";
			} else {
				if(!$token) $token=" ";
				$expression .= $token ;
			}
	  	}
		if($expression) $expr[] = $this->process_select_expr(trim($expression));
		return $expr;
	}
	
	/* This fuction processes each SELECT clause.  We determine what (if any) alias
	   is provided, and we set the type of expression.
	*/
	private function process_select_expr($expression) {
		$tokens = $this->split_sql($expression);

		$as_at = 0;
		$alias = "";
		$base_expr = "";
		for($i=0;$i<count($tokens);++$i) {
			if(strtoupper($tokens[$i]) == 'AS') {
				$pos = $i+1;
				while($alias == "" && $pos <= count($tokens)) {
					$alias = trim($tokens[$pos]);
					++$pos;
				}
				break;
			} elseif($i<count($tokens)-1 ) {
			    $base_expr .= $tokens[$i];
			} 
	
		}
	
		/* If the last two tokens are not reserved words, then the last word must be the alias*/
	        /* TODO: profile this and determine if hash lookup would be faster */
		if(count($tokens) > 1) {
			if(!in_array(strtoupper($tokens[count($tokens)-1]), $this->reserved) &&
			   !in_array(strtoupper($tokens[count($tokens)-2]), $this->reserved)) { 
				$alias = $tokens[count($tokens)-1];
				
			} else {
				$base_expr .= $tokens[count($tokens)-1];
			}
		}
	
		/* If there is no alias, then make the alias the properly escaped contents of the entire expression */
	        if (!$alias) {
			$alias = $expression;
	        } 
	
		/* Properly escape the alias if it is not escaped */
	        if ($alias[0] != '`') {
				$alias = '`' . str_replace('`','``',$alias) . '`';
		}
		$processed = false;
		if(!$base_expr) $base_expr = trim($expression);
		if($base_expr[0] == '(') {
			$base_expr = substr($base_expr,1,-1);
			if(preg_match('/^sel/i', $base_expr)) {
				$type='subquery';
				$processed = $this->parse($base_expr);
			}
		}

		if(!$processed) {
			$type = 'expression';
			$processed = $this->process_expr_list($this->split_sql($base_expr));
		}
		

		if(count($processed) == 1) {
			$processed = $processed[0];
			$processed['alias'] = $alias;
		} else {
			$processed = array('alias' => $alias, 'expr_type' => $type, 'sub_tree' => $processed);
		}

		return $processed;
	
	}

		
	private function process_from(&$tokens) {
		$expression = "";
		$expr = array();
		$token_count=0;
		$table = "";
	        $alias = "";
	
		$skip_next=false;
		$i=0;
	        $join_type = '';
		$ref_type="";
		$ref_expr="";
		$base_expr="";
		$sub_tree = false;
		foreach($tokens as $token) {
			$base_expr = false;
			$upper = strtoupper(trim($token));
	
			if($skip_next && $token) {
				$token_count++;
				$skip_next = false;
				continue;
			} else {
				if($skip_next) {
					continue;
				}
			}
			if(preg_match("/^\\s*\\(\\s*select/i",$token)) {
				$type = 'subquery';
				$table = "DEPENDENT-SUBQUERY";
				$base_expr = $token;
				$sub_tree = $this->parse(trim($token,'() '));
			}
	
			if($upper != 'JOIN' && $token != ',') {
				$expression .= $token == '' ? " " : $token;
				if($ref_type) {
					$ref_expr .= $token == '' ? " " : $token;
				}
	                }
	
			$is_keyword = false;
		
			switch($upper) {
				case 'AS':
					$token_count++;
					$n=1;
					$alias = "";
					while($alias == "") {
						$alias = trim($tokens[$i+$n]);
						++$n;
					} 
	
					continue;
				break;
	
				case 'INDEX':
	
					if($token_category == 'CREATE') {
						$token_category = $upper;
						continue 2;
					}
	
				break;
	
				case 'USING':
				case 'ON':
				case 'NATURAL':
					$ref_type = strtoupper($token);
					$ref_expr = "";
	
				case 'CROSS':
				case 'USE':
				case 'FORCE':
				case 'IGNORE':
				case 'INNER':
				case 'OUTER':
				#	$expression .= $token;
					$token_count++;
					continue;
				break;
	
					
				case 'LEFT':
				case 'RIGHT':
				case 'STRAIGHT_JOIN':
					$join_type .= ($token == '' ? ' ' : $token . " ");
					$ref_expr = "";
					continue;
				break;
	
	                        case 'FOR':
					$token_count++;
					$skip_next = true;
					continue;
				break;
	
	                        case ',':
				case 'JOIN':
					#any options for the first join (the one after the FROM) are "stuck" to the first
	             			#table.  we need to use 'FROM' as the join_type for the first table, and save the
	 				#"stuck" options for the next table
					$save_join_type = "";	
					if(count($expr)>0) {
						$join_type .= $token == ',' ? ' JOIN' : $upper;
					} else {
						$save_join_type = $join_type;
						$join_type = 'JOIN';
					}
					if(!trim($alias)) $alias = $table;
		
					$join_type=strtoupper($join_type);
					/*if(!$base_expr) {
						$expr[] = array('table'=>$table, 'alias'=>$alias,'join_type'=>$join_type,'ref_type'=> $ref_type,'ref_clause'=> $ref_expr);
					} else {*/
						$expr[] = array('table'=>$table, 'alias'=>$alias,'join_type'=>$join_type,'ref_type'=> $ref_type,'ref_clause'=> $ref_expr, 'base_expr' => $base_expr, 'sub_tree' => $sub_tree);
		#			} 

					if($save_join_type) {
						$join_type = $save_join_type;
					} else {
						$join_type = "";
					}
					$token_count = 0;
	    				$table = $alias = $expression = $base_expr = $ref_type = $ref_expr = "";
					 $sub_tree = false;

				break;
	
				default:
					if(!$token) continue;
	
					if($token_count == 0 ) { 
						if(!$table) {	
							$table = $token ;
						}
					} else if($token_count == 1) {
						$alias = $token;	
					}
					$token_count++;
				break;	
			}
			++$i;
	  	}
			if($join_type == '') $join_type = 'JOIN';
			if(!trim($alias)) $alias = $table;
			/*if(!$base_expr) {
				$expr[] = array('table'=>$table, 'alias'=>$alias,'join_type'=>$join_type,'ref_type'=> $ref_type,'ref_clause'=> $ref_expr);
			} else { */
				$expr[] = array('table'=>$table, 'alias'=>$alias,'join_type'=>$join_type,'ref_type'=> $ref_type,'ref_clause'=> $ref_expr, 'base_expr' => $base_expr, 'sub_tree' => $sub_tree);
		#	} 

		return $expr;
	}
	
	private function process_group(&$tokens, &$select) {
	
		$out=array();
		$expression = "";
		$direction="ASC";
	        $type = "expression";
		if(!$tokens) return false;
	
		foreach($tokens as $token) {
			switch(strtoupper($token)) {
				case ',':
					$expression = trim($expression);
					if($expression[0] != '`' || substr($expression,-1) != '`') {
						$escaped = str_replace('`','``',$expression);
					} else {
						$escaped = $expression;
					}
					$escaped = '`' . $escaped . '`';
		
					if(is_numeric(trim($expression))) {
						$type = 'pos';	
					} else {
	
					  	#search to see if the expression matches an alias
						foreach($select as $clause) {
							if($clause['alias'] == $escaped) {
								$type = 'alias';
							}	
						}
	
						if(!$type) $type = "expression";
					}
	
					$out[]=array('type'=>$type,'expression'=>$expression,'direction'=>$direction);
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
		if($expression) {
				$expression = trim($expression);
				if($expression[0] != '`' || substr($expression,-1) != '`') {
					$escaped = str_replace('`','``',$expression);
				} else {
					$escaped = $expression;
				}
				$escaped = '`' . $escaped . '`';
		
				if(is_numeric(trim($expression))) {
					$type = 'pos';	
				} else {
	
					#search to see if the expression matches an alias
					if(!$type && $select) {
						foreach($select as $clause) {
							if(!is_array($clause)) continue;
							if($clause['alias'] == $escaped) {
								$type = 'alias';
							}	
						}
					} else {
						$type="expression";
					}

					if(!$type) $type = "expression";
				}
			
				$out[]=array('type'=>$type,'expression'=>$expression,'direction'=>$direction);
		}
	
		return $out;
	}
	
	/* Some sections are just lists of expressions, like the WHERE and HAVING clauses.  This function
           processes these sections.  Recursive.
	*/
	private function process_expr_list($tokens) {
		$expr = "";
		$type = "";
		$prev_token = "";
		foreach($tokens as $key => $token) {
	
			if(!trim($token)) continue;
			
			$processed = false;
			$upper = strtoupper(trim($token));
			if(trim($token)) $token=trim($token);

			/* is it a subquery?*/
			if(preg_match("/^\\s*\\(\\s*SELECT/i", $token)) {
				$type = 'subquery';
				#tokenize and parse the subquery.
				#we remove the enclosing parenthesis for the tokenizer
				$processed = $this->parse(trim($token,' ()'));


			/* is it an inlist */
			} elseif( $upper[0] == '(' && substr($upper,-1) == ')' ) {
				if($prev_token == 'IN') {
					$type = "in-list";
					$processed = $token;
					$prev_token = "";
				} 
			/* it is either an operator, a colref or a constant */
			} else {
				switch($upper) {
				case 'AND':
				case '&&':
				case 'BETWEEN':
				case 'AND':
				case 'BINARY':
				case '&':
				case '~':
				case '|':
				case '^':
				case 'CASE':
				case 'WHEN':
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
				case '*':
				case '-':
				case 'XOR':
				case 'IN':
						$processed = false;
						$type = "operator";
						break;
				default: 
					switch($token[0]) {
						case "'":
						case '"':
								$type = 'const';
								break;
						case '`':
								$type = 'colref';
								break;
							
						default:
							if(is_numeric($token)) {
								$type = 'const';
							} else {
								$type = 'colref';
							}
						break;
				
					}	
					#$processed = $token;
					$processed = false;
				}
			}
			/* is a reserved word? */
			if(($type != 'operator' && $type != 'in-list' && $type != 'sub_expr') && in_array($upper, $this->reserved)) {
				$type = 'reserved';	
				$token = $upper;
				$processed = false;
			}

			if(!$type) {
				if($upper[0] == '(') {
					$local_expr = substr(trim($token),1,-1);
				} else {
					$local_expr = $token;
				}
                                $processed = $this->process_expr_list($this->split_sql($local_expr));
				$type = 'expression';
				if(count($processed) == 1) {
					$type = $processed[0]['expr_type'];
					$base_expr  = $processed[0]['base_expr'];
					$processed = $processed[0]['sub_tree'];
				}
			}

			$expr[] = array( 'expr_type' => $type, 'base_expr' => $token, 'sub_tree' => $processed);
			$prev_token = $upper;
			$expr_type = "";
			$type = "";
		}
		if(!is_array($processed)) $processed = false;

		if($expr_type) {
			$expr[] = array( 'expr_type' => $type, 'base_expr' => $token, 'sub_tree' => $processed);
		}

		return $expr;
	} 

	private function process_update($tokens) {

	}

	private function process_delete($tokens) {
		$tables = array();
		$del = $tokens['DELETE'];

		foreach($tokens['DELETE'] as $expression) {
			if ($expression != 'DELETE' && trim($expression,' .*') != "" && $expression != ',') {
				$tables[] = trim($expression,'.* ');	
			}
		}

		if(empty($tables)) {
			foreach($tokens['FROM'] as $table) {
				$tables[] = $table['table'];
			}
		}

		$tokens['DELETE'] = array('TABLES' => $tables);

		return $tokens;
	}

	function process_insert($tokens, $token_category = 'INSERT') {
		$table = "";
		$cols = "";

		$into = $tokens['INTO'];
		foreach($into as $token) {
			if(!trim($token)) continue;
			if(!$table) {
				$table = $token;
			}elseif(!$cols) {
				$cols = $token;
			}
		}

		if(!$cols) {
			$cols = 'ALL';
		} else {
			$cols = explode(",", trim($cols,'() '));
		}
		unset($tokens['INTO']);
		$tokens[$token_category] =  array('table'=>$table, 'cols'=>$cols);
		return $tokens;

	}
	

	function load_reserved_words() {
		$words = <<<EOWORDS
ACCESSIBLE
ADD
ALL
ALTER
ANALYZE
AND
AS
ASC
ASENSITIVE
BEFORE
BETWEEN
BIGINT
BINARY
BLOB
BOTH
BY
CALL
CASCADE
CASE
CHANGE
CHAR
CHARACTER
CHECK
COLLATE
COLUMN
CONDITION
CONSTRAINT
CONTINUE
CONVERT
CREATE
CROSS
CURRENT_DATE
CURRENT_TIME
CURRENT_TIMESTAMP
CURRENT_USER
CURSOR
DATABASE
DATABASES
DAY_HOUR
DAY_MICROSECOND
DAY_MINUTE
DAY_SECOND
DEC
DECIMAL
DECLARE
DEFAULT
DELAYED
DELETE
DESC
DESCRIBE
DETERMINISTIC
DISTINCT
DISTINCTROW
DIV
DOUBLE
DROP
DUAL
EACH
ELSE
ELSEIF
ENCLOSED
ESCAPED
EXISTS
EXIT
EXPLAIN
FALSE
FETCH
FLOAT
FLOAT4
FLOAT8
FOR
FORCE
FOREIGN
FROM
FULLTEXT
GRANT
GROUP
HAVING
HIGH_PRIORITY
HOUR_MICROSECOND
HOUR_MINUTE
HOUR_SECOND
IF
IGNORE
IN
INDEX
INFILE
INNER
INOUT
INSENSITIVE
INSERT
INT
INT1
INT2
INT3
INT4
INT8
INTEGER
INTERVAL
INTO
IS
ITERATE
JOIN
KEY
KEYS
KILL
LEADING
LEAVE
LEFT
LIKE
LIMIT
LINEAR
LINES
LOAD
LOCALTIME
LOCALTIMESTAMP
LOCK
LONG
LONGBLOB
LONGTEXT
LOOP
LOW_PRIORITY
MASTER_SSL_VERIFY_SERVER_CERT
MATCH
MEDIUMBLOB
MEDIUMINT
MEDIUMTEXT
MIDDLEINT
MINUTE_MICROSECOND
MINUTE_SECOND
MOD
MODIFIES
NATURAL
NOT
NO_WRITE_TO_BINLOG
NULL
NUMERIC
ON
OPTIMIZE
OPTION
OPTIONALLY
OR
ORDER
OUT
OUTER
OUTFILE
PRECISION
PRIMARY
PROCEDURE
PURGE
RANGE
READ
READS
READ_WRITE
REAL
REFERENCES
REGEXP
RELEASE
RENAME
REPEAT
REPLACE
REQUIRE
RESTRICT
RETURN
REVOKE
RIGHT
RLIKE
SCHEMA
SCHEMAS
SECOND_MICROSECOND
SELECT
SENSITIVE
SEPARATOR
SET
SHOW
SMALLINT
SPATIAL
SPECIFIC
SQL
SQLEXCEPTION
SQLSTATE
SQLWARNING
SQL_BIG_RESULT
SQL_CALC_FOUND_ROWS
SQL_SMALL_RESULT
SSL
STARTING
STRAIGHT_JOIN
TABLE
TERMINATED
THEN
TINYBLOB
TINYINT
TINYTEXT
TO
TRAILING
TRIGGER
TRUE
UNDO
UNION
UNIQUE
UNLOCK
UNSIGNED
UPDATE
USAGE
USE
USING
UTC_DATE
UTC_TIME
UTC_TIMESTAMP
VALUES
VARBINARY
VARCHAR
VARCHARACTER
VARYING
WHEN
WHERE
WHILE
WITH
WRITE
XOR
YEAR_MONTH
ZEROFILL
AVG
BIT_AND
BIT_OR
BIT_XOR
COUNT
GROUP_CONCAT
MAX
MIN
STD
STDDEV_POP
STDDEV_SAMP
STDDEV
SUM
VAR_POP
VAR_SAMP
VARIANCE
GET_LOCK
INET_ATON
INET_NTOA
IS_FREE_LOCK
IS_USED_LOCK
MASTER_POS_WAIT
NAME_CONST
RAND
RELEASE_LOCK
SLEEP
UUID_SHORT
UUID
SOUNDS
NULLIF
IFNULL
STRCMP
ASCII
BIN
BIT_LENGTH
CHAR_LENGTH
CHARACTER_LENGTH
CONCAT_WS
CONCAT
ELT
EXPORT_SET
FIELD
FIND_IN_SET
FORMAT
HEX
INSTR
LCASE
LENGTH
LOAD_FILE
LOCATE
LOWER
LPAD
LTRIM
MAKE_SET
MID
OCTET_LENGTH
ORD
POSITION
QUOTE
REVERSE
RPAD
RTRIM
SOUNDEX
SPACE
SUBSTR
SUBSTRING_INDEX
SUBSTRING
TRIM
UCASE
UNHEX
UPPER
ABS
ACOS
ASIN
ATAN2
ATAN
CEIL
CEILING
CONV
COS
COT
CRC32
DEGREES
EXP
FLOOR
LN
LOG10
LOG2
LOG
OCT
PI
POW
POWER
RADIANS
ROUND
SIGN
SIN
SQRT
TAN
TRUNCATE
ADDDATE
ADDTIME
CONVERT_TZ
CURDATE
CURTIME
DATE_ADD
DATE_FORMAT
DATE_SUB
DATE
DATEDIFF
DAY
DAYNAME
DAYOFMONTH
DAYOFWEEK
DAYOFYEAR
EXTRACT
FROM_DAYS
FROM_UNIXTIME
GET_FORMAT
HOUR
LAST_DAY
LOCALTIMESTAMP,
MAKEDATE
MAKETIME
MICROSECOND
MINUTE
MONTH
MONTHNAME
NOW
PERIOD_ADD
PERIOD_DIFF
QUARTER
SEC_TO_TIME
SECOND
STR_TO_DATE
SUBDATE
SUBTIME
SYSDATE
TIME_FORMAT
TIME_TO_SEC
TIME
TIMEDIFF
TIMESTAMP
TIMESTAMPADD
TIMESTAMPDIFF
TO_DAYS
UNIX_TIMESTAMP
WEEK
WEEKDAY
WEEKOFYEAR
YEAR
YEARWEEK
AGAINST
CAST
EXTRACTVALUE
UPDATEXML
BIT_COUNT
AES_DECRYPT
AES_ENCRYPT
COMPRESS
DECODE
DES_DECRYPT
DES_ENCRYPT
ENCODE
ENCRYPT
MD5
OLD_PASSWORD
PASSWORD
SHA1
UNCOMPRESS
UNCOMPRESSED_LENGTH
BENCHMARK
CHARSET
COERCIBILITY
COLLATION
CONNECTION_ID
FOUND_ROWS
LAST_INSERT_ID
ROW_COUNT
SESSION_USER
SYSTEM_USER
USER
VERSION
END
+
-
=
>
>
<>
!=
IN
*
%
,
||
&&
==
^
EOWORDS;

	
		$this->reserved = explode("\n", $words);
	}

} // END CLASS

