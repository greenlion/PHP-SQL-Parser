<?php

require_once('php-sql-parser.php');
require_once('php-sql-creator.php');

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

    private function replaceKeyword($colref, $search, $replace, &$out) {
        $colpos = $this->getColumnNamePos($colref, $search);
        if ($colpos === false) {
            return $colref;
        }
        $out = substr($colref, 0, $colpos) . $replace . substr($colref, $colpos + strlen($search));
    }

    private function replaceASWithinAlias($alias, &$out) {
        if ($alias === false || $alias['as'] === false) {
            return;
        }
        $out['base_expr'] = preg_replace('/as/i', '', $alias['base_expr'], 1);
    }

    private function replaceLongTableName($table, $needle, $replacement, &$out) {
        if (strcasecmp($table, $needle) == 0) {
            $out = $replacement;
        }
    }

    private function handleSelect($parsed, &$out) {
        foreach ($parsed as $k => $v) {

            switch ($v['expr_type']) {
            case 'colref': # add alias.* if we have only *!
                $this->replaceKeyword($v['base_expr'], "uid", "uid_", $out[$k]['base_expr']);
                break;
            case 'subquery':
                $this->replaceASWithinAlias($v['alias'], $out[$k]['alias']);
                $this->processSQL($parsed[$k]['sub_tree'], $out[$k]['sub_tree']);
                break;
            default:
            }

            
            // remove all other columns, if we have 
            // a colref * without table alias
        }
    }

    
    private function handleRefClause($parsed, &$out) {
        if ($parsed === false) {
            return;
        }
        foreach ($parsed as $k => $v) {
            if ($v['expr_type'] === 'colref') {
                $this->replaceKeyword($v['base_expr'], "uid", "uid_", $out[$k]['base_expr']);            
            }
        }
    }
    
    private function handleFrom($parsed, &$out) {
        foreach ($parsed as $k => $v) {

            $this->replaceASWithinAlias($v['alias'], $out[$k]['alias']);
            $this->replaceLongTableName($v['table'], 'surveys_languagesettings', 'surveys_lngsettings', $out[$k]['table']);
            $this->handleRefClause($v['ref_clause'], $out[$k]['ref_clause']);
            
            // subquery?
        }

    }

    private function handleWhere($parsed, &$out) {
        foreach ($parsed as $k => $v) {

            switch ($v['expr_type']) {
            case 'subquery':
                $this->processSQL($v['base_expr'], $parsed[$k]['sub_tree'], $out[$k]['sub_tree']);
                break;
            case 'colref':
                $this->replaceKeyword($v['base_expr'], "uid", "uid_", $out[$k]['base_expr']);
                break;
            default:
            }
            // colref = alias.clob ?
        }
    }

    private function handleGroupBy($parsed, &$out) {
        foreach ($parsed as $k => $v) {
            if ($v['expr_type'] == 'colref') {
                $this->replaceKeyword($v['base_expr'], "uid", "uid_", $out[$k]['base_expr']);
            }
        }
    }

    private function handleOrderBy($parsed, &$out) {
        foreach ($parsed as $k => $v) {
            if ($v['type'] == 'expression') {
                $this->replaceKeyword($v['base_expr'], "uid", "uid_", $out[$k]['base_expr']);
                //$this->replaceLOBColumn($sql, $v['base_expr'], $v['position']);
            }
        }
    }

    private function handleSelectStatement($parsed, &$out) {

        foreach ($parsed as $k => $v) {
            switch ($k) {
            case 'SELECT':
                $this->handleSelect($parsed[$k], $out[$k]);
                break;
            case 'FROM':
                $this->handleFrom($parsed[$k], $out[$k]);
                break;
            case 'WHERE':
                $this->handleWhere($parsed[$k], $out[$k]);
                break;
            case 'GROUP':
                $this->handleGroupBy($parsed[$k], $out[$k]);
                break;
            case 'ORDER':
                $this->handleOrderBy($parsed[$k], $out[$k]);
                break;
            default:
                safe_die("unknown SELECT statement part " . $k);
            }
        }
    }

    private function handleUpdating($parsed, &$out) {
        foreach ($parsed as $k => $v) {
            $this->replaceLongTableName($v['table'],
                    "surveys_languagesettings", "surveys_lngsettings", $out[$k]['table']);
        }
    }

    private function handleInsert($parsed, &$out) {

        $this->replaceLongTableName($parsed['table'],
                "surveys_languagesettings", "surveys_lngsettings", $out['table']);

        if ($parsed['columns']) { # all columns part
            foreach ($parsed['columns'] as $k => $v) {
                $this->replaceKeyword($v['base_expr'], "uid", "uid_", $out['columns'][$k]['base_expr']);
            }
        }
    }

    private function handleSet($parsed, &$out) {
        foreach ($parsed as $k => $v) {
            $this->replaceKeyword($v['column'], "uid", "uid_", $out[$k]['column']);

            // colref = alias.clob ?
            // subquery?
        }

    }

    private function handleValues($parsed, &$out) {
        // ignore it at the moment
        // some subqueries?
    }

    private function handleInsertStatement($parsed, &$out) {
        foreach ($parsed as $k => $v) {
            switch ($k) {
            case 'INSERT':
                $this->handleInsert($parsed[$k], $out[$k]);
                break;
            case 'VALUES':
                $this->handleValues($parsed[$k], $out[$k]);
                break;
            default:
                safe_die("unknown INSERT statement part " . $k);
            }
        }
    }

    private function handleDeleteStatement($parsed, &$out) {
        safe_die($this->preprint($parsed, true));
    }

    private function handleUpdateStatement($parsed, &$out) {
        foreach ($parsed as $k => $v) {
            switch ($k) {
            case 'UPDATE':
                $this->handleUpdating($parsed[$k], $out[$k]);
                break;
            case 'SET':
                $this->handleSet($parsed[$k], $out[$k]);
                break;
            case 'WHERE':
                $this->handleWhere($parsed[$k], $out[$k]);
                break;
            default:
                safe_die("unknown UPDATE statement part " . $k);
            }
        }
    }

    private function processSQLStatement($parsed, &$out) {
        $k = key($parsed);
        switch ($k) {
        case 'SELECT':
            $this->handleSelectStatement($parsed, $out);
            break;
        case 'INSERT':
            $this->handleInsertStatement($parsed, $out);
            break;
        case 'UPDATE':
            $this->handleUpdateStatement($parsed, $out);
            break;
        case 'DELETE':
            $this->handleDeleteStatement($parsed, $out);
            break;
        default:
            safe_die("invalid SQL type " . key($parsed));
        }
    }

    # prevents parsing of create-oracle.php statements
    # maybe we run into problems with Limesurvey internal creation statements
    private function isNativeStatement($sql) {
        $sql = trim($sql);
        if (stripos($sql, "CREATE") === 0) {
            return true;
        }
        if (stripos($sql, "ALTER") === 0) {
            return true;
        }
        if (stripos($sql, "COMMENT") === 0) {
            return true;
        }
        if (stripos($sql, "USE") === 0) {
            return true;
        }
        return false;
    }

    public function process($sql) {
        if ($this->isNativeStatement($sql)) {
            return $sql;
        }

        $parser = new PHPSQLParser($sql, true);
        $parsed = $parser->parsed;

        echo "before: " . $this->preprint($sql, true) . "\n";
        print_r($parsed);
        $this->processSQLStatement($parser->parsed, $parsed);
        print_r($parsed);

        $creator = new PHPSQLCreator($parsed);
        $sql = $creator->created;
        echo "after: " . $this->preprint($sql, true) . "\n";
        return $sql;
    }

    public function getAllTables($sql) {
        $parser = new PHPSQLParser($sql);
        $parsed = $parser->parsed;
        return $this->extractAllTables($parsed, array());
    }
}

$parser = new OracleSQLParser(false);
//$parser->process("SELECT a.*, c.*, u.users_name FROM SURVEYS as a  INNER JOIN SURVEYS_LANGUAGESETTINGS as c ON ( surveyls_survey_id = a.sid AND surveyls_language = a.language ) AND surveyls_survey_id=a.sid and surveyls_language=a.language  INNER JOIN USERS as u ON (u.uid=a.owner_id)  ORDER BY surveyls_title");
//$parser->process(" SELECT a.*, surveyls_title, surveyls_description, surveyls_welcometext, surveyls_url  FROM SURVEYS AS a INNER JOIN SURVEYS_LANGUAGESETTINGS on (surveyls_survey_id=a.sid and surveyls_language=a.language)  order by active DESC, surveyls_title");
//$parser->process(" INSERT INTO settings_global VALUES ('DBVersion', '146')");
//$parser->process("CREATE TABLE answers ( qid number(11) default '0' NOT NULL, code varchar2(5) default '' NOT NULL, answer CLOB NOT NULL, assessment_value number(11) default '0' NOT NULL, sortorder number(11) NOT NULL, language varchar2(20) default 'en', scale_id number(3) default '0' NOT NULL, PRIMARY KEY (qid,code,language,scale_id) )");
//$parser->process("USE DATABASE `sdbprod`");
//$parser->process("insert into SETTINGS_GLOBAL (stg_value,stg_name) values('','force_ssl')");
//$parser->process("SELECT * FROM SETTINGS_GLOBAL");
//$parser->process("SELECT stg_value FROM SETTINGS_GLOBAL where stg_name='force_ssl'");
$parser->process("update SETTINGS_GLOBAL set stg_value='' where stg_name='force_ssl'");
//$parser->process("SELECT * FROM FAILED_LOGIN_ATTEMPTS WHERE ip='172.18.47.211'");
