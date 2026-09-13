<?php
namespace PHPSQLParser\builders;

class WithStatementBuilder implements Builder {
    public function build(array $parsed) {
        $sql = 'WITH ';
        $ctes = [];

        foreach ($parsed['WITH'] as $cte) {
            // The CTE definition is a flat array of tokens
            if (!isset($cte['sub_tree']) || !is_array($cte['sub_tree']) || count($cte['sub_tree']) < 3) {
                continue;
            }
            $tokens = $cte['sub_tree'];
            // Get the CTE name
            $aliasName = isset($tokens[0]['base_expr']) ? $tokens[0]['base_expr'] : '';
            // Get the subquery
            $subTree = isset($tokens[2]['sub_tree']) ? $tokens[2]['sub_tree'] : null;
            if (!$aliasName || !$subTree) {
                continue;
            }
            $cte_sql = $aliasName . ' AS (' . (new SelectStatementBuilder())->build($subTree) . ')';
            $ctes[] = $cte_sql;
        }

        $sql .= implode(', ', $ctes);

        // Pass all top-level keys except WITH to the SelectStatementBuilder
        $mainQuery = $parsed;
        unset($mainQuery['WITH']);
        if (!empty($mainQuery)) {
            $sql .= ' ' . (new SelectStatementBuilder())->build($mainQuery);
        }

        return $sql;
    }
}