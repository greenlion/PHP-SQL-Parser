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

    private function replaceASWithinAlias(&$sql, $start, $table, $alias) {
        if (strcmp($table, $alias)) {
            // # is a delimiter and will be ignrored
            $pattern = "#" . $table . "\\s+AS\\s+" . $alias . "#i";
            $part = preg_replace($pattern, $table . "  " . $alias, substr($sql, $start), 1);
            $sql = substr($sql, 0, $start) . $part;
        }
    }

    private function replaceLongTableName(&$sql, $start, $table, $needle, $replacement) {
        if (!strcasecmp($table, $needle)) {
            $sql = substr($sql, 0, $start) . $replacement . substr($sql, $start + strlen($needle));
        }
    }

    private function handleSelect(&$sql, &$parsed) {
        foreach ($parsed as $k => $v) {

            switch ($v['expr_type']) {
            case 'colref':
                $this->replaceKeyword($sql, $v['base_expr'], $v['position'], "uid", "uid_");
                break;
            case 'subquery':
                $this->replaceASWithinAlias($sql, $v['position'], $v['base_expr'], $v['alias']);
                $this->processSQL($sql, $curr, $parsed[$k]['sub_tree']);

                // both methods change the $sql, so we cannot search for parts
                // we must change the base_expr too

                break;
            default:
            }

            // replace the colref * in some cases
        }
    }

    private function handleFrom(&$sql, &$parsed) {
        foreach ($parsed as $k => $v) {

            $this->replaceASWithinAlias($sql, $v['position'], $v['table'], $v['alias']);
            $this->replaceLongTableName($sql, $v['position'], $v['table'],
                    'surveys_languagesettings', 'surveys_lngsettings');

            // 'ref_clause' look for uid
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
                $this->replaceKeyword($sql, $v['base_expr'], $v['position'], "uid", "uid_");
                break;
            default:
            }
            // colref = alias.clob ?
        }
    }

    private function handleGroupBy(&$sql, &$parsed) {
        foreach ($parsed as $k => $v) {
            if ($v['expr_type'] == 'colref') {
                $this->replaceKeyword($sql, $v['base_expr'], $v['position'], "uid", "uid_");
            }
        }
    }

    private function handleOrderBy(&$sql, &$parsed) {
        foreach ($parsed as $k => $v) {
            if ($v['type'] == 'expression') {
                $this->replaceKeyword($sql, $v['base_expr'], $v['position'], "uid", "uid_");
                $this->replaceLOBColumn($sql, $v['base_expr'], $v['position']);
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

    private function handleInserting(&$sql, &$parsed) {

        $this->replaceLongTableName($sql, $parsed['position'], $parsed['table'],
                'surveys_languagesettings', 'surveys_lngsettings');

        foreach ($parsed['cols'] as $k => $v) {

            $curr = stripos($sql, $v, $curr);
            $parsed['cols'][$k] = array('colref' => $curr, 'pos' => $curr);

            $this->replaceKeyword($sql, $v, $curr, "uid", "uid_");
        }
    }

    private function handleSet(&$sql, &$curr, &$parsed) {
        foreach ($parsed as $k => $v) {
            $curr = stripos($sql, $v['column'], $curr);
            $parsed[$k]['pos'] = $curr;

            // we have to check the alias.uid!!
            $this->replaceKeyword($sql, $v['column'], $curr, "uid", "uid_");

            // colref = alias.clob ?
            // subquery?
        }

    }

    private function handleValues(&$sql, &$curr, &$parsed) {
        // ignore it at the moment
    }

    private function handleInsertStatement($sql, $parsed) {
        foreach ($parsed as $k => $v) {
            switch ($k) {
            case 'INSERT':
                $curr = stripos($sql, 'INSERT', $curr);
                $this->handleInserting($sql, $curr, $parsed[$k]);
                break;
            case 'VALUES':
                $curr = stripos($sql, 'VALUES', $curr);
                $this->handleValues($sql, $curr, $parsed[$k]);
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
                $curr = stripos($sql, 'UPDATE', $curr);
                $this->handleUpdating($sql, $curr, $parsed[$k]);
                break;
            case 'SET':
                $curr = stripos($sql, 'SET', $curr);
                $this->handleSet($sql, $curr, $parsed[$k]);
                break;
            case 'WHERE':
                $curr = stripos($sql, 'WHERE', $curr);
                $this->handleWhere($sql, $curr, $parsed[$k]);
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
        default:
            safe_die("invalid SQL type " . key($parsed));
        }
    }

    public function process($sql) {
        $parser = new PHPSQLParser($sql, true);
        $parsed = $parser->parsed;

        $this->preprint($parsed);
        $this->processSQLStatement($sql, $parsed);
        $this->preprint($parsed);

        return $sql;
    }

    public function getAllTables($sql) {
        $parser = new PHPSQLParser($sql);
        $parsed = $parser->parsed;
        return $this->extractAllTables($parsed, array());
    }
}

$parser = new OracleSQLParser(false);
$parser->process("SELECT * FROM SETTINGS_GLOBAL");
$parser->process("SELECT stg_value FROM SETTINGS_GLOBAL where stg_name='force_ssl'");
$parser->process("update SETTINGS_GLOBAL set stg_value='' where stg_name='force_ssl'");
$parser->process("insert into SETTINGS_GLOBAL (stg_value,stg_name) values('','force_ssl')");
$parser->process("SELECT * FROM FAILED_LOGIN_ATTEMPTS WHERE ip='172.18.47.211'");
