<?php
error_reporting(E_ALL);

class PHPSQLParser {
	var $reserved = array();
	var $functions = array();
	function __construct($sql = false) {
		#LOAD THE LIST OF RESERVED WORDS
		$this->load_reserved_words();
		if($sql) $this->parse($sql);
	}

	function parse($sql) {
		$sql = trim($sql);

		#lex the SQL statement
		$in = $this->split_sql($sql);
	
		#sometimes the parser needs to skip ahead until a particular 
		#token is found
		$skip_until = false;
		
		#this is the output tree which is being parsed
		$out = array();

		#This is the last type of union used (UNION or UNION ALL)
		#indicates a) presence of at least one union in this query
		#          b) the type of union if this is the first or last query
		$union = false;

		#Sometimes a "query" consists of more than one query (like a UNION query)
		#this array holds all the queries
		$queries=array();

		#This is the highest level lexical analysis.  This is the part of the
		#code which finds UNION and UNION ALL query parts
		foreach($in as $key => $token) {
			$token=trim($token);
		
			if($skip_until) {
				if($token) {
					if(strtoupper($token) == $skip_until) {
						$skip_until = false;
						continue;
					}
				} else { 
					continue;
				}
			}

			if(strtoupper($token) == "UNION") {
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
				$out[]=$token;
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

		This block handles this query syntax.  Only one such subquery
		is supported in each UNION block.  (select)(select)union(select) is not legal.
		The extra queries will be silently ignored.
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

		
		/* If there was no UNION or UNION ALL in the query, then the query is 
		stored at $queries[0].
		*/
		if(!empty($queries[0])) {
			$queries[0] = $this->process_sql($queries[0]);
			
		}

		if(count($queries) == 1 && !$union) {
			$queries = $queries[0];
		}

		$this->parsed = $queries;
		return $this->parsed;
	}

	#This function counts open and close parenthesis and
	#returns their location.  This might be faster as a regex
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

	#This is the lexer
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
		$token_count = count($tokens);

		/* The above regex has one problem, because the parenthetical match is not greedy.
		   Thus, when matching grouped expresions such as ( (a and b) or c) the 
		   tokenizer will produce "( (a and b)", " ", "or", " " , "c,")" 
		

		   This block detects the number of open/close parens in the given token.  If the parens are balanced
		   (balanced == 0) then we don't need to do anything.
		
		   otherwise, we need to balance the expression.
	        */
		$reset = false;
		for($i=0;$i<$token_count;++$i) {
			if(empty($tokens[$i])) continue;
			$trim = trim($tokens[$i]);
			if($trim) $tokens[$i] = $trim;
			$token=$trim;
			if($token && $token[0] == '(') {
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
						$reset = true;
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
							$reset = true;
						}
						$tokens[$i] .= $str1;
						$info2 = $this->count_paren($tokens[$i]);
						$needed = abs($info2['balanced']);

					}
				}
			}
		}
		/* reset the array if we deleted any tokens above */
	        return  $reset ? array_values($tokens) : $tokens;
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
		$capture = false;
		$alias = "";
		$base_expression = $expression;
		#if necessary, unpack the expression
		if($expression[0] == '(') {
			$expression = substr($expression,1,-1);
			$base_expression = $expression;
		}

		$tokens = $this->split_sql($expression);
		$token_count = count($tokens);
		
		/* Determine if there is an explicit alias after the AS clause.
		If AS is found, then the next non-whitespace token is captured as the alias.
		The tokens after (and including) the AS are removed.
		*/
		$base_expr = "";
		$stripped=array();
		$capture=false;
		$alias = "";
		for($i=0;$i<$token_count;++$i) {
			$token = strtoupper($tokens[$i]);
			if(trim($token)) {
				$stripped[] = $tokens[$i];
			}

			if($token == 'AS') {
				unset($tokens[$i]);
				$capture = true;
				continue;
			} 

			if($capture) {
				if(trim($token)) {
					$alias .= $tokens[$i];
				}
				unset($tokens[$i]);
				continue;
			}
			$base_expr .= $tokens[$i];
		}

		$stripped = $this->process_expr_list($stripped);
		$last = array_pop($stripped);
		if(!$alias && $last['expr_type'] == 'colref') {
			$prev = array_pop($stripped);			
			if($prev['expr_type'] == 'operator' || 
			   $prev['expr_type'] == 'function' ||
			   $prev['expr_type'] == 'expression' ||
			   $prev['expr_type'] == 'aggregate_function' || 
			   $prev['expr_type'] == 'subquery' ||	
			   $prev['expr_type'] == 'colref') {
				$alias = $last['base_expr'];

				#remove the last token
				array_pop($tokens);

				$base_expr = join("", $tokens);
			}
		}
		

		if(!$alias) {
			$base_expr=join("", $tokens);
			$alias = $base_expr;
		}
	
		/* Properly escape the alias if it is not escaped */
	        if ($alias[0] != '`') {
				$alias = '`' . str_replace('`','``',$alias) . '`';
		}
		$processed = false;
		$type='expression';
		if(trim($base_expr) == '(') {
			$base_expr = substr($expression,1,-1);
			if(preg_match('/^sel/i', $base_expr)) {
				$type='subquery';
				$processed = $this->parse($base_expr);
			}
		}
		if(!$processed) {
			$processed = $this->process_expr_list($tokens);
		}

		if(count($processed) == 1) {
			$type = $processed[0]['expr_type'];
			$processed = false;
		}

		return array('expr_type'=>$type,'alias' => $alias, 'base_expr' => $base_expr, 'sub_tree' => $processed);
	
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
		$subquery = "";
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
				$sub_tree = $this->parse(trim($token,'() '));
				$subquery = $token;
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
						if($subquery) $base_expr=$subquery;
						$expr[] = array('table'=>$table, 'alias'=>$alias,'join_type'=>$join_type,'ref_type'=> $ref_type,'ref_clause'=> $ref_expr, 'base_expr' => $base_expr, 'sub_tree' => $sub_tree);
						$subquery = "";
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
			
				$out[]=array('type'=>$type,'base_expr'=>$expression,'direction'=>$direction);
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
		$skip_next = false;
		$sub_expr = "";

		$in_lists = array();
		foreach($tokens as $key => $token) {
	
			if(!trim($token)) continue;
			if($skip_next) {
				$skip_next = false;
				continue;
			}
			
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
					$processed = $this->split_sql(substr($token,1,-1));
					$list = array();
					foreach($processed as $v) {
						if($v == ',') continue;
						$list[]=$v;
					}
					$processed = $list;
					unset($list);
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
			if(($type != 'operator' && $type != 'in-list' && $type != 'sub_expr') && !empty($this->reserved[$upper])) {
				$token = $upper;
				if(empty($this->functions[$upper])) {
					$type = 'reserved';	
				} else {
					switch($token) {
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
							if(!empty($tokens[$key+1])) $sub_expr = $tokens[$key+1];
							#$skip_next=true;
						break;

						default:
							$type = 'function';
							$sub_expr = $tokens[$key+1];
							#$skip_next=true;

				
						break;
					}
				}
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

			$sub_expr=trim($sub_expr);
			$sub_expr = "";

			$expr[] = array( 'expr_type' => $type, 'base_expr' => $token, 'sub_tree' => $processed);
			$prev_token = $upper;
			$expr_type = "";
			$type = "";
		}
		if($sub_expr) {
			$processed['sub_tree'] = $this->process_expr_list($this->split_sql(substr($sub_expr,1,-1)));
		}
		
		if(!is_array($processed)) $processed = false;

		if($expr_type) {
			$expr[] = array( 'expr_type' => $type, 'base_expr' => $token, 'sub_tree' => $processed);
		}
		$mod = false;

/*

		for($i=0;$i<count($expr);++$i){
			if($expr[$i]['expr_type'] == 'function' ||
			   $expr[$i]['expr_type'] == 'aggregate_function') {
				if(!empty($expr[$i+1])) {
					$expr[$i]['sub_tree']=$expr[$i+1]['sub_tree'];
					unset($expr[$i+1]);
					$mod = 1;
					++$i;  // BAD FORM TO MODIFY THE LOOP COUNTER
				}
			} 
				
		}

*/

		if($mod) $expr=array_values($expr);

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
$this->functions = array('ABS'=>1,'ACOS'=>1,'ADDDATE'=>1,'ADDTIME'=>1,'AES_ENCRYPT'=>1,'AES_DECRYPT'=>1,'AGAINST'=>1,'ASCII'=>1,'ASIN'=>1,'ATAN'=>1,'AVG'=>1,'BENCHMARK'=>1,'BIN'=>1,'BIT_AND'=>1,'BIT_OR'=>1,'BITCOUNT'=>1,'BITLENGTH'=>1,'CAST'=>1,'CEILING'=>1,'CHAR'=>1,'CHAR_LENGTH'=>1,'CHARACTER_LENGTH'=>1,'CHARSET'=>1,'COALESCE'=>1,'COERCIBILITY'=>1,'COLLATION'=>1,'COMPRESS'=>1,'CONCAT'=>1,'CONCAT_WS'=>1,'CONECTION_ID'=>1,'CONV'=>1,'CONVERT'=>1,'CONVERT_TZ'=>1,'COS'=>1,'COT'=>1,'COUNT'=>1,'CRC32'=>1,'CURDATE'=>1,'CURRENT_USER'=>1,'CURRVAL'=>1,'CURTIME'=>1,'DATABASE'=>1,'DATE_ADD'=>1,'DATE_DIFF'=>1,'DATE_FORMAT'=>1,'DATE_SUB'=>1,'DAY'=>1,'DAYNAME'=>1,'DAYOFMONTH'=>1,'DAYOFWEEK'=>1,'DAYOFYEAR'=>1,'DECODE'=>1,'DEFAULT'=>1,'DEGREES'=>1,'DES_DECRYPT'=>1,'DES_ENCRYPT'=>1,'ELT'=>1,'ENCODE'=>1,'ENCRYPT'=>1,'EXP'=>1,'EXPORT_SET'=>1,'EXTRACT'=>1,'FIELD'=>1,'FIND_IN_SET'=>1,'FLOOR'=>1,'FORMAT'=>1,'FOUND_ROWS'=>1,'FROM_DAYS'=>1,'FROM_UNIXTIME'=>1,'GET_FORMAT'=>1,'GET_LOCK'=>1,'GROUP_CONCAT'=>1,'GREATEST'=>1,'HEX'=>1,'HOUR'=>1,'IF'=>1,'IFNULL'=>1,'IN'=>1,'INET_ATON'=>1,'INET_NTOA'=>1,'INSERT'=>1,'INSTR'=>1,'INTERVAL'=>1,'IS_FREE_LOCK'=>1,'IS_USED_LOCK'=>1,'LAST_DAY'=>1,'LAST_INSERT_ID'=>1,'LCASE'=>1,'LEAST'=>1,'LEFT'=>1,'LENGTH'=>1,'LN'=>1,'LOAD_FILE'=>1,'LOCALTIME'=>1,'LOCALTIMESTAMP'=>1,'LOCATE'=>1,'LOG'=>1,'LOG2'=>1,'LOG10'=>1,'LOWER'=>1,'LPAD'=>1,'LTRIM'=>1,'MAKE_SET'=>1,'MAKEDATE'=>1,'MAKETIME'=>1,'MASTER_POS_WAIT'=>1,'MATCH'=>1,'MAX'=>1,'MD5'=>1,'MICROSECOND'=>1,'MID'=>1,'MIN'=>1,'MINUTE'=>1,'MOD'=>1,'MONTH'=>1,'MONTHNAME'=>1,'NEXTVAL'=>1,'NOW'=>1,'NULLIF'=>1,'OCT'=>1,'OCTET_LENGTH'=>1,'OLD_PASSWORD'=>1,'ORD'=>1,'PASSWORD'=>1,'PERIOD_ADD'=>1,'PERIOD_DIFF'=>1,'PI'=>1,'POSITION'=>1,'POW'=>1,'POWER'=>1,'QUARTER'=>1,'QUOTE'=>1,'RADIANS'=>1,'RAND'=>1,'RELEASE_LOCK'=>1,'REPEAT'=>1,'REPLACE'=>1,'REVERSE'=>1,'RIGHT'=>1,'ROUND'=>1,'ROW_COUNT'=>1,'RPAD'=>1,'RTRIM'=>1,'SEC_TO_TIME'=>1,'SECOND'=>1,'SESSION_USER'=>1,'SHA'=>1,'SHA1'=>1,'SIGN'=>1,'SOUNDEX'=>1,'SPACE'=>1,'SQRT'=>1,'STD'=>1,'STDDEV'=>1,'STDDEV_POP'=>1,'STDDEV_SAMP'=>1,'STRCMP'=>1,'STR_TO_DATE'=>1,'SUBDATE'=>1,'SUBSTRING'=>1,'SUBSTRING_INDEX'=>1,'SUBTIME'=>1,'SUM'=>1,'SYSDATE'=>1,'SYSTEM_USER'=>1,'TAN'=>1,'TIME'=>1,'TIMEDIFF'=>1,'TIMESTAMP'=>1,'TIMESTAMPADD'=>1,'TIMESTAMPDIFF'=>1,'TIME_FORMAT'=>1,'TIME_TO_SEC'=>1,'TO_DAYS'=>1,'TRIM'=>1,'TRUNCATE'=>1,'UCASE'=>1,'UNCOMPRESS'=>1,'UNCOMPRESSED_LENGTH'=>1,'UNHEX'=>1,'UNIX_TIMESTAMP'=>1,'UPPER'=>1,'USER'=>1,'UTC_DATE'=>1,'UTC_TIME'=>1,'UTC_TIMESTAMP'=>1,'UUID'=>1,'VAR_POP'=>1,'VAR_SAMP'=>1,'VARIANCE'=>1,'VERSION'=>1,'WEEK'=>1,'WEEKDAY'=>1,'WEEKOFYEAR'=>1,'YEAR'=>1,'YEARWEEK'=>1);


$this->reserved = array('ABS'=>1,'ACOS'=>1,'ADDDATE'=>1,'ADDTIME'=>1,'AES_ENCRYPT'=>1,'AES_DECRYPT'=>1,'AGAINST'=>1,'ASCII'=>1,'ASIN'=>1,'ATAN'=>1,'AVG'=>1,'BENCHMARK'=>1,'BIN'=>1,'BIT_AND'=>1,'BIT_OR'=>1,'BITCOUNT'=>1,'BITLENGTH'=>1,'CAST'=>1,'CEILING'=>1,'CHAR'=>1,'CHAR_LENGTH'=>1,'CHARACTER_LENGTH'=>1,'CHARSET'=>1,'COALESCE'=>1,'COERCIBILITY'=>1,'COLLATION'=>1,'COMPRESS'=>1,'CONCAT'=>1,'CONCAT_WS'=>1,'CONECTION_ID'=>1,'CONV'=>1,'CONVERT'=>1,'CONVERT_TZ'=>1,'COS'=>1,'COT'=>1,'COUNT'=>1,'CRC32'=>1,'CURDATE'=>1,'CURRENT_USER'=>1,'CURRVAL'=>1,'CURTIME'=>1,'DATABASE'=>1,'DATE_ADD'=>1,'DATE_DIFF'=>1,'DATE_FORMAT'=>1,'DATE_SUB'=>1,'DAY'=>1,'DAYNAME'=>1,'DAYOFMONTH'=>1,'DAYOFWEEK'=>1,'DAYOFYEAR'=>1,'DECODE'=>1,'DEFAULT'=>1,'DEGREES'=>1,'DES_DECRYPT'=>1,'DES_ENCRYPT'=>1,'ELT'=>1,'ENCODE'=>1,'ENCRYPT'=>1,'EXP'=>1,'EXPORT_SET'=>1,'EXTRACT'=>1,'FIELD'=>1,'FIND_IN_SET'=>1,'FLOOR'=>1,'FORMAT'=>1,'FOUND_ROWS'=>1,'FROM_DAYS'=>1,'FROM_UNIXTIME'=>1,'GET_FORMAT'=>1,'GET_LOCK'=>1,'GROUP_CONCAT'=>1,'GREATEST'=>1,'HEX'=>1,'HOUR'=>1,'IF'=>1,'IFNULL'=>1,'IN'=>1,'INET_ATON'=>1,'INET_NTOA'=>1,'INSERT'=>1,'INSTR'=>1,'INTERVAL'=>1,'IS_FREE_LOCK'=>1,'IS_USED_LOCK'=>1,'LAST_DAY'=>1,'LAST_INSERT_ID'=>1,'LCASE'=>1,'LEAST'=>1,'LEFT'=>1,'LENGTH'=>1,'LN'=>1,'LOAD_FILE'=>1,'LOCALTIME'=>1,'LOCALTIMESTAMP'=>1,'LOCATE'=>1,'LOG'=>1,'LOG2'=>1,'LOG10'=>1,'LOWER'=>1,'LPAD'=>1,'LTRIM'=>1,'MAKE_SET'=>1,'MAKEDATE'=>1,'MAKETIME'=>1,'MASTER_POS_WAIT'=>1,'MATCH'=>1,'MAX'=>1,'MD5'=>1,'MICROSECOND'=>1,'MID'=>1,'MIN'=>1,'MINUTE'=>1,'MOD'=>1,'MONTH'=>1,'MONTHNAME'=>1,'NEXTVAL'=>1,'NOW'=>1,'NULLIF'=>1,'OCT'=>1,'OCTET_LENGTH'=>1,'OLD_PASSWORD'=>1,'ORD'=>1,'PASSWORD'=>1,'PERIOD_ADD'=>1,'PERIOD_DIFF'=>1,'PI'=>1,'POSITION'=>1,'POW'=>1,'POWER'=>1,'QUARTER'=>1,'QUOTE'=>1,'RADIANS'=>1,'RAND'=>1,'RELEASE_LOCK'=>1,'REPEAT'=>1,'REPLACE'=>1,'REVERSE'=>1,'RIGHT'=>1,'ROUND'=>1,'ROW_COUNT'=>1,'RPAD'=>1,'RTRIM'=>1,'SEC_TO_TIME'=>1,'SECOND'=>1,'SESSION_USER'=>1,'SHA'=>1,'SHA1'=>1,'SIGN'=>1,'SOUNDEX'=>1,'SPACE'=>1,'SQRT'=>1,'STD'=>1,'STDDEV'=>1,'STDDEV_POP'=>1,'STDDEV_SAMP'=>1,'STRCMP'=>1,'STR_TO_DATE'=>1,'SUBDATE'=>1,'SUBSTRING'=>1,'SUBSTRING_INDEX'=>1,'SUBTIME'=>1,'SUM'=>1,'SYSDATE'=>1,'SYSTEM_USER'=>1,'TAN'=>1,'TIME'=>1,'TIMEDIFF'=>1,'TIMESTAMP'=>1,'TIMESTAMPADD'=>1,'TIMESTAMPDIFF'=>1,'TIME_FORMAT'=>1,'TIME_TO_SEC'=>1,'TO_DAYS'=>1,'TRIM'=>1,'TRUNCATE'=>1,'UCASE'=>1,'UNCOMPRESS'=>1,'UNCOMPRESSED_LENGTH'=>1,'UNHEX'=>1,'UNIX_TIMESTAMP'=>1,'UPPER'=>1,'USER'=>1,'UTC_DATE'=>1,'UTC_TIME'=>1,'UTC_TIMESTAMP'=>1,'UUID'=>1,'VAR_POP'=>1,'VAR_SAMP'=>1,'VARIANCE'=>1,'VERSION'=>1,'WEEK'=>1,'WEEKDAY'=>1,'WEEKOFYEAR'=>1,'YEAR'=>1,'YEARWEEK'=>1,'ADD'=>1,'ALL'=>1,'ALTER'=>1,'ANALYZE'=>1,'AND'=>1,'AS'=>1,'ASC'=>1,'ASENSITIVE'=>1,'AUTO_INCREMENT'=>1,'BDB'=>1,'BEFORE'=>1,'BERKELEYDB'=>1,'BETWEEN'=>1,'BIGINT'=>1,'BINARY'=>1,'BLOB'=>1,'BOTH'=>1,'BY'=>1,'CALL'=>1,'CASCADE'=>1,'CASE'=>1,'CHANGE'=>1,'CHARACTER'=>1,'CHECK'=>1,'COLLATE'=>1,'COLUMN'=>1,'COLUMNS'=>1,'CONDITION'=>1,'CONNECTION'=>1,'CONSTRAINT'=>1,'CONTINUE'=>1,'CREATE'=>1,'CROSS'=>1,'CURRENT_DATE'=>1,'CURRENT_TIME'=>1,'CURRENT_TIMESTAMP'=>1,'CURSOR'=>1,'DATABASES'=>1,'DAY_HOUR'=>1,'DAY_MICROSECOND'=>1,'DAY_MINUTE'=>1,'DAY_SECOND'=>1,'DEC'=>1,'DECIMAL'=>1,'DECLARE'=>1,'DELAYED'=>1,'DELETE'=>1,'DESC'=>1,'DESCRIBE'=>1,'DETERMINISTIC'=>1,'DISTINCT'=>1,'DISTINCTROW'=>1,'DIV'=>1,'DOUBLE'=>1,'DROP'=>1,'ELSE'=>1,'ELSEIF'=>1,'ENCLOSED'=>1,'ESCAPED'=>1,'EXISTS'=>1,'EXIT'=>1,'EXPLAIN'=>1,'FALSE'=>1,'FETCH'=>1,'FIELDS'=>1,'FLOAT'=>1,'FOR'=>1,'FORCE'=>1,'FOREIGN'=>1,'FOUND'=>1,'FRAC_SECOND'=>1,'FROM'=>1,'FULLTEXT'=>1,'GRANT'=>1,'GROUP'=>1,'HAVING'=>1,'HIGH_PRIORITY'=>1,'HOUR_MICROSECOND'=>1,'HOUR_MINUTE'=>1,'HOUR_SECOND'=>1,'IGNORE'=>1,'INDEX'=>1,'INFILE'=>1,'INNER'=>1,'INNODB'=>1,'INOUT'=>1,'INSENSITIVE'=>1,'INT'=>1,'INTEGER'=>1,'INTO'=>1,'IO_THREAD'=>1,'IS'=>1,'ITERATE'=>1,'JOIN'=>1,'KEY'=>1,'KEYS'=>1,'KILL'=>1,'LEADING'=>1,'LEAVE'=>1,'LIKE'=>1,'LIMIT'=>1,'LINES'=>1,'LOAD'=>1,'LOCK'=>1,'LONG'=>1,'LONGBLOB'=>1,'LONGTEXT'=>1,'LOOP'=>1,'LOW_PRIORITY'=>1,'MASTER_SERVER_ID'=>1,'MEDIUMBLOB'=>1,'MEDIUMINT'=>1,'MEDIUMTEXT'=>1,'MIDDLEINT'=>1,'MINUTE_MICROSECOND'=>1,'MINUTE_SECOND'=>1,'NATURAL'=>1,'NOT'=>1,'NO_WRITE_TO_BINLOG'=>1,'NULL'=>1,'NUMERIC'=>1,'ON'=>1,'OPTIMIZE'=>1,'OPTION'=>1,'OPTIONALLY'=>1,'OR'=>1,'ORDER'=>1,'OUT'=>1,'OUTER'=>1,'OUTFILE'=>1,'PRECISION'=>1,'PRIMARY'=>1,'PRIVILEGES'=>1,'PROCEDURE'=>1,'PURGE'=>1,'READ'=>1,'REAL'=>1,'REFERENCES'=>1,'REGEXP'=>1,'RENAME'=>1,'REQUIRE'=>1,'RESTRICT'=>1,'RETURN'=>1,'REVOKE'=>1,'RLIKE'=>1,'SECOND_MICROSECOND'=>1,'SELECT'=>1,'SENSITIVE'=>1,'SEPARATOR'=>1,'SET'=>1,'SHOW'=>1,'SMALLINT'=>1,'SOME'=>1,'SONAME'=>1,'SPATIAL'=>1,'SPECIFIC'=>1,'SQL'=>1,'SQLEXCEPTION'=>1,'SQLSTATE'=>1,'SQLWARNING'=>1,'SQL_BIG_RESULT'=>1,'SQL_CALC_FOUND_ROWS'=>1,'SQL_SMALL_RESULT'=>1,'SQL_TSI_DAY'=>1,'SQL_TSI_FRAC_SECOND'=>1,'SQL_TSI_HOUR'=>1,'SQL_TSI_MINUTE'=>1,'SQL_TSI_MONTH'=>1,'SQL_TSI_QUARTER'=>1,'SQL_TSI_SECOND'=>1,'SQL_TSI_WEEK'=>1,'SQL_TSI_YEAR'=>1,'SSL'=>1,'STARTING'=>1,'STRAIGHT_JOIN'=>1,'STRIPED'=>1,'TABLE'=>1,'TABLES'=>1,'TERMINATED'=>1,'THEN'=>1,'TINYBLOB'=>1,'TINYINT'=>1,'TINYTEXT'=>1,'TO'=>1,'TRAILING'=>1,'TRUE'=>1,'UNDO'=>1,'UNION'=>1,'UNIQUE'=>1,'UNLOCK'=>1,'UNSIGNED'=>1,'UPDATE'=>1,'USAGE'=>1,'USE'=>1,'USER_RESOURCES'=>1,'USING'=>1,'VALUES'=>1,'VARBINARY'=>1,'VARCHAR'=>1,'VARCHARACTER'=>1,'VARYING'=>1,'WHEN'=>1,'WHERE'=>1,'WHILE'=>1,'WITH'=>1,'WRITE'=>1,'XOR'=>1,'YEAR_MONTH'=>1,'ZEROFILL'=>1);
	}

} // END CLASS
