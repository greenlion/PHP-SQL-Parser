PHP-SQL-Parser
==============

A pure PHP SQL (non validating) parser w/ focus on MySQL dialect of SQL


###Download

 [http://code.google.com/p/php-sql-parser/wiki/Downloads](http://code.google.com/p/php-sql-parser/wiki/Downloads)<br>
 [http://php-sql-parser.googlecode.com/svn](http://php-sql-parser.googlecode.com/svn)
    
###Full support for the MySQL dialect for the following statement types

    SELECT
    INSERT
    UPDATE
    DELETE
    REPLACE
    SET
    DROP
    CREATE INDEX
    CREATE TABLE 

###Other SQL statement types

Other statements are returned as an array of tokens. This is not as structured as the information available about the above types. See the ParserManual for more information.

###Other SQL dialects

Since the MySQL SQL dialect is very close to SQL-92, this should work for most database applications that need a SQL parser. If using another database dialect, then you may want to change the reserved words - see the ParserManual. It supports UNION, subqueries and compound statements.

###External dependencies

The parser is a self contained class. It has no external dependencies. The parser uses a small amount of regex.

###Focus

The focus of the parser is complete and accurate support for the MySQL SQL dialect. The focus is not on optimizing for performance. It is expected that you will present syntactically valid queries.

###Manual

ParserManual - Check out the manual here

###Example Output

**Example Query**

```sql
SELECT STRAIGHT_JOIN a,b,c 
  from some_table an_alias
WHERE d > 5;
```

**Example Output (via print_r)**

```php
Array
( 
    [OPTIONS] => Array
        (
            [0] => STRAIGHT_JOIN
        )       
        
    [SELECT] => Array
        (
            [0] => Array
                (
                    [expr_type] => colref
                    [base_expr] => a
                    [sub_tree] => 
                    [alias] => `a`
                )

            [1] => Array
                (
                    [expr_type] => colref
                    [base_expr] => b
                    [sub_tree] => 
                    [alias] => `b`
                )

            [2] => Array
                (
                    [expr_type] => colref
                    [base_expr] => c
                    [sub_tree] => 
                    [alias] => `c`
                )

        )

    [FROM] => Array
        (
            [0] => Array
                (
                    [table] => some_table
                    [alias] => an_alias
                    [join_type] => JOIN
                    [ref_type] => 
                    [ref_clause] => 
                    [base_expr] => 
                    [sub_tree] => 
                )

        )

    [WHERE] => Array
        (
            [0] => Array
                (
                    [expr_type] => colref
                    [base_expr] => d
                    [sub_tree] => 
                )

            [1] => Array
                (
                    [expr_type] => operator
                    [base_expr] => >
                    [sub_tree] => 
                )

            [2] => Array
                (
                    [expr_type] => const
                    [base_expr] => 5
                    [sub_tree] => 
                )

        )

)
```
