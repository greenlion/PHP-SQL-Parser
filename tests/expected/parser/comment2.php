<?php
return array (
  'SELECT' => 
  array (
    0 => 
    array (
      'expr_type' => 'colref',
      'alias' => false,
      'base_expr' => 'a',
      'no_quotes' => 
      array (
        'delim' => false,
        'parts' => 
        array (
          0 => 'a',
        ),
      ),
      'sub_tree' => false,
      'delim' => ',',
    ),
    1 => 
    array (
      'expr_type' => 'comment',
      'value' => '/* 
                            multi line 
                            comment
                        */',
    ),
    2 => 
    array (
      'expr_type' => 'colref',
      'alias' => false,
      'base_expr' => 'b',
      'no_quotes' => 
      array (
        'delim' => false,
        'parts' => 
        array (
          0 => 'b',
        ),
      ),
      'sub_tree' => false,
      'delim' => false,
    ),
  ),
  'FROM' => 
  array (
    0 => 
    array (
      'expr_type' => 'table',
      'table' => 'test',
      'no_quotes' => 
      array (
        'delim' => false,
        'parts' => 
        array (
          0 => 'test',
        ),
      ),
      'alias' => false,
      'hints' => false,
      'join_type' => 'JOIN',
      'ref_type' => false,
      'ref_clause' => false,
      'base_expr' => 'test',
      'sub_tree' => false,
    ),
  ),
);