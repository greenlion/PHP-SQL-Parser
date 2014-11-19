<?php
namespace PHPSQLParser\builders;

/**
 * This class implements the builder for the whole Union statement. You can overwrite
 * all functions to achieve another handling.
 *
 * @author  George Schneeloch <george_schneeloch@hms.harvard.edu>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class UnionStatementBuilder implements Builder {

	public function build(array $parsed)
	{
		$sql = '';
		$select_builder = new SelectBuilder();
		$from_builder = new FromBuilder();
		$first = true;
		foreach ($parsed['UNION'] as $clause) {
			if (!$first) {
				$sql .= " UNION ";
			}
			else {
				$first = false;
			}

			foreach ($clause as $k => $v) {
				if ($k === 'SELECT') {
					$sql .= $select_builder->build($v) . " ";
				}
				elseif ($k === 'FROM') {
					$sql .= $from_builder->build($v) . " ";
				}
				else {
					throw new \Exception("Unexpected key found: $k");
				}
			}
		}
		var_dump($sql);
		return $sql;
	}
}