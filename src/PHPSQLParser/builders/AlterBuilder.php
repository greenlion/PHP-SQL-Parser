<?php

namespace PHPSQLParser\builders;

use PHPSQLParser\utils\ExpressionType;

/**
 * This class implements the builder for the [DELETE] part. You can overwrite
 * all functions to achieve another handling.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class AlterBuilder implements Builder
{
    public function build(array $parsed)
    {
        return "ALTER " . $parsed['base_expr'];
    }
}
