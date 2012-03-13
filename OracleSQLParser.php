<?php

require_once('php-sql-parser.php');

class OracleSQLParser {

    var $con;

    public function __construct($con) {
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

    private function extractAllTables($sql, $arr) {
        $result = $arr;
        $tab = array();

        foreach ($sql as $k => $v) {
            if (is_array($v)) {
                $result = $this->extractAllTables($v, $result);
            } else {
                // 'alias' follows 'table'!
                if ($k == 'table') {
                    $tab['table'] = $v;
                }
                if (($k == 'alias') && (key_exists('table', $tab))) {
                    if ($v == $tab['table']) {
                        $tab['alias'] = ""; // this can be a problem
                    } else {
                        $tab['alias'] = $v;
                    }
                }
            }
        }
        if (key_exists('alias', $tab)) {
            array_push($result, $tab);
        }
        return $result;
    }

    private function getLobColumnsOfTable($table) {
        $oldFetchMode = $this->con->fetchMode;
        $this->con->SetFetchMode(ADODB_FETCH_NUM);
        $result = $this->con->GetAll(
                "SELECT column_name FROM user_lobs WHERE table_name=upper('" . $table . "')");
        $this->con->SetFetchMode($oldFetchMode);
        return $result;
    }

    private function getQualifiedColumnName($table, $column) {
        if (empty($table['alias'])) {
            return $column[0];
        }
        return $table['alias'] . "." . $column[0];
    }

    private function replaceLOBColumn(&$sql, $colref, $start) {
        $tables = $this->getAllTables($sql);
        while (sizeof($tables) > 0) {
            $tableInfo = array_pop($tables);
            $lobCols = $this->getLobColumnsOfTable($tableInfo['table']);

            while (sizeof($lobCols) > 0) {
                $columnInfo = $this->getQualifiedColumnName($tableInfo, array_pop($lobCols));
                if (!strcasecmp($colref, $columnInfo)) {
                    $sql = substr($sql, 0, $start) . "cast(substr(" . $columnInfo
                            . ",1,200) as varchar2(200))"
                            . substr($sql, $start + strlen($columnInfo));
                }
            }
        }
    }

    private function getColumnNamePos($string, $search) {
        $column = strpos($string, ".");
        if ($column === false) {
            if (!strcasecmp($search, $string)) {
                return 0;
            }
            return false;
        }
        if (strcasecmp($search, substr($string, $column + 1))) {
            return false;
        }
        return $column + 1;
    }

    private function replaceKeyword(&$sql, $colref, $pos, $search, $replace) {
        $colpos = $this->getColumnNamePos($colref, $search);
        if ($colpos !== false) {
            $sql = substr($sql, 0, $pos + $colpos) . $replace
                    . substr($sql, $pos + $colpos + strlen($search));
        }
    }

    private function replaceASWithinAlias(&$sql, &$alias) {
        if ($alias === false || $alias['as'] === false) {
            return;
        }

        // the base_expr starts with regex("\s+as\s+")
        // the AS will be replaced with two space characters to hold a stable position within $sql
        for ($i = 0; $i < strlen($alias['base_expr']); $i++) {
            if (strtoupper($alias['base_expr'][$i]) !== "A") {
                continue;
            }
            $sql = substr($sql, 0, $alias['position'] + $i) . "  "
                    . substr($sql, $alias['position'] + $i + 2);
            break;
        }
    }

    private function replaceLongTableName(&$sql, $start, $table, $needle, $replacement) {
        if (strcasecmp($table, $needle) == 0) {
            # hold a stable position within $sql
            $spaces = "";
            for ($i = 0; $i < strlen($needle) - strlen($replacement); $i++) {
                $spaces .= " ";
            }
            $sql = substr($sql, 0, $start) . $replacement . $spaces
                    . substr($sql, $start + strlen($needle));
        }
    }

    private function handleSelect(&$sql, &$parsed) {
        foreach ($parsed as $k => $v) {

            switch ($v['expr_type']) {
            case 'colref':
                $this->replaceKeyword($sql, $v['base_expr'], $v['position'], "uid", "diu");
                break;
            case 'subquery':
                $this->replaceASWithinAlias($sql, $v['alias']);
                $this->processSQL($sql, $parsed[$k]['sub_tree']);
                break;
            default:
            }

            // replace the colref * in some cases
        }
    }

    
    private function handleRefClause(&$sql, &$parsed) {
        if ($parsed === false) {
            return;
        }
        foreach ($parsed as $k => $v) {
            if ($v['expr_type'] === 'colref') {
                $this->replaceKeyword($sql, $v['base_expr'], $v['position'], "uid", "diu");            
            }
        }
    }
    
    private function handleFrom(&$sql, &$parsed) {
        foreach ($parsed as $k => $v) {

            $this->replaceASWithinAlias($sql, $v['alias']);
            $this->replaceLongTableName($sql, $v['position'], $v['table'],
                    'surveys_languagesettings', 'surveys_lngsettings');

            $this->handleRefClause($sql, $v['ref_clause']);
            
            // subquery?
        }

    }

    private function handleWhere(&$sql, &$parsed) {
        foreach ($parsed as $k => $v) {

            switch ($v['expr_type']) {
            case 'subquery':
                $this->processSQL($sql, $v['base_expr'], $parsed[$k]['sub_tree']);
                break;
            case 'colref':
                $this->replaceKeyword($sql, $v['base_expr'], $v['position'], "uid", "diu");
                break;
            default:
            }
            // colref = alias.clob ?
        }
    }

    private function handleGroupBy(&$sql, &$parsed) {
        foreach ($parsed as $k => $v) {
            if ($v['expr_type'] == 'colref') {
                $this->replaceKeyword($sql, $v['base_expr'], $v['position'], "uid", "diu");
            }
        }
    }

    private function handleOrderBy(&$sql, &$parsed) {
        foreach ($parsed as $k => $v) {
            if ($v['type'] == 'expression') {
                $this->replaceKeyword($sql, $v['base_expr'], $v['position'], "uid", "diu");
                //$this->replaceLOBColumn($sql, $v['base_expr'], $v['position']);
            }
        }
    }

    private function handleSelectStatement(&$sql, &$parsed) {

        foreach ($parsed as $k => $v) {
            switch ($k) {
            case 'SELECT':
                $this->handleSelect($sql, $parsed[$k]);
                break;
            case 'FROM':
                $this->handleFrom($sql, $parsed[$k]);
                break;
            case 'WHERE':
                $this->handleWhere($sql, $parsed[$k]);
                break;
            case 'GROUP':
                $this->handleGroupBy($sql, $parsed[$k]);
                break;
            case 'ORDER':
                $this->handleOrderBy($sql, $parsed[$k]);
                break;
            default:
                safe_die("unknown SELECT statement part " . $k);
            }
        }
    }

    private function handleUpdating(&$sql, &$parsed) {
        foreach ($parsed as $k => $v) {
            $this->replaceLongTableName($sql, $v['position'], $v['table'],
                    'surveys_languagesettings', 'surveys_lngsettings');
        }
    }

    private function handleInsert(&$sql, &$parsed) {

        $this->replaceLongTableName($sql, $parsed['position'], $parsed['table'],
                'surveys_languagesettings', 'surveys_lngsettings');

        if ($parsed['columns']) { # all column statement
            foreach ($parsed['columns'] as $k => $v) {
                $this->replaceKeyword($sql, $v['base_expr'], $v['position'], "uid", "diu");
            }
        }
    }

    private function handleSet(&$sql, &$parsed) {
        foreach ($parsed as $k => $v) {
            $this->replaceKeyword($sql, $v['column'], $v['position'], "uid", "diu");

            // colref = alias.clob ?
            // subquery?
        }

    }

    private function handleValues(&$sql, &$parsed) {
        // ignore it at the moment
        // some subqueries?
    }

    private function handleInsertStatement($sql, $parsed) {
        foreach ($parsed as $k => $v) {
            switch ($k) {
            case 'INSERT':
                $this->handleInsert($sql, $parsed[$k]);
                break;
            case 'VALUES':
                $this->handleValues($sql, $parsed[$k]);
                break;
            default:
                safe_die("unknown INSERT statement part " . $k);
            }
        }
    }

    private function handleDeleteStatement($sql, $parsed) {
        safe_die($sql);
    }

    private function handleUpdateStatement($sql, $parsed) {
        foreach ($parsed as $k => $v) {
            switch ($k) {
            case 'UPDATE':
                $this->handleUpdating($sql, $parsed[$k]);
                break;
            case 'SET':
                $this->handleSet($sql, $parsed[$k]);
                break;
            case 'WHERE':
                $this->handleWhere($sql, $parsed[$k]);
                break;
            default:
                safe_die("unknown UPDATE statement part " . $k);
            }
        }
    }

    private function processSQLStatement(&$sql, &$parsed) {
        $k = key($parsed);
        switch ($k) {
        case 'SELECT':
            $this->handleSelectStatement($sql, $parsed);
            break;
        case 'INSERT':
            $this->handleInsertStatement($sql, $parsed);
            break;
        case 'UPDATE':
            $this->handleUpdateStatement($sql, $parsed);
            break;
        case 'DELETE':
            $this->handleDeleteStatement($sql, $parsed);
            break;
        case 'USE': # use database, it is not necessary
            $sql = "";
            break;
        default:
            safe_die("invalid SQL type " . key($parsed));
        }
    }

    # prevents parsing of create-oracle.php statements
    # maybe we run into problems with Limesurvey internal creation statements
    private function isOracleStatement($sql) {
        $sql = trim($sql);
        if (stripos($sql, "CREATE") !== false) {
            return true;
        }
        if (stripos($sql, "ALTER") !== false) {
            return true;
        }
        if (stripos($sql, "COMMENT") !== false) {
            return true;
        }
        return false;
    }

    public function process($sql) {
        if ($this->isOracleStatement($sql)) {
            return $sql;
        }

        $parser = new PHPSQLParser($sql, true);
        $parsed = $parser->parsed;

        echo "before: " . $this->preprint($sql, true) . "\n";
        print_r($parsed);
        $this->processSQLStatement($sql, $parsed);
        echo "after: " . $this->preprint($sql, true) . "\n";

        return rtrim(trim($sql), ';');
    }

    public function getAllTables($sql) {
        $parser = new PHPSQLParser($sql);
        $parsed = $parser->parsed;
        return $this->extractAllTables($parsed, array());
    }
}

$parser = new OracleSQLParser(false);
$parser->process("SELECT a.*, c.*, u.users_name FROM SURVEYS as a  INNER JOIN SURVEYS_LANGUAGESETTINGS as c ON ( surveyls_survey_id = a.sid AND surveyls_language = a.language ) AND surveyls_survey_id=a.sid and surveyls_language=a.language  INNER JOIN USERS as u ON (u.uid=a.owner_id)  ORDER BY surveyls_title");
//$parser->process(" SELECT a.*, surveyls_title, surveyls_description, surveyls_welcometext, surveyls_url  FROM SURVEYS AS a INNER JOIN SURVEYS_LANGUAGESETTINGS on (surveyls_survey_id=a.sid and surveyls_language=a.language)  order by active DESC, surveyls_title");
//$parser->process(" INSERT INTO settings_global VALUES ('DBVersion', '146')");
//$parser->process("CREATE TABLE answers ( qid number(11) default '0' NOT NULL, code varchar2(5) default '' NOT NULL, answer CLOB NOT NULL, assessment_value number(11) default '0' NOT NULL, sortorder number(11) NOT NULL, language varchar2(20) default 'en', scale_id number(3) default '0' NOT NULL, PRIMARY KEY (qid,code,language,scale_id) )");
//$parser->process("USE DATABASE `sdbprod`");
//$parser->process("insert into SETTINGS_GLOBAL (stg_value,stg_name) values('','force_ssl')");
//$parser->process("SELECT * FROM SETTINGS_GLOBAL");
//$parser->process("SELECT stg_value FROM SETTINGS_GLOBAL where stg_name='force_ssl'");
//$parser->process("update SETTINGS_GLOBAL set stg_value='' where stg_name='force_ssl'");
//$parser->process("SELECT * FROM FAILED_LOGIN_ATTEMPTS WHERE ip='172.18.47.211'");
