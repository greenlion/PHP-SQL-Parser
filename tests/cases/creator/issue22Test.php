<?php
/**
 * issue22Test.php
 *
 * Test case for PHPSQLCreator.
 *
 * PHP version 5
 *
 * LICENSE:
 * Copyright (c) 2010-2014 Justin Swanhart and André Rothe
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author    André Rothe <andre.rothe@phosco.info>
 * @copyright 2010-2014 Justin Swanhart and André Rothe
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @version   SVN: $Id$
 *
 */
namespace PHPSQLParser\Test\Creator;

use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;

class Issue22Test extends \PHPUnit_Framework_TestCase {

	/**
	 * https://github.com/greenlion/PHP-SQL-Parser/issues/22
	 */
	public function testIssue22() {
		$sql    = "CREATE TABLE IF NOT EXISTS wp_md_3_term_taxonomy (term_taxonomy_id bigint (20) NOT NULL auto_increment, term_id bigint (20) NOT NULL default 0, taxonomy varchar (32) NOT NULL default '', description longtext NOT NULL, parent bigint (20) NOT NULL default 0, count bigint (20) NOT NULL default 0, PRIMARY KEY (term_taxonomy_id), KEY term_id_taxonomy (term_id, taxonomy), KEY taxonomy (taxonomy)) DEFAULT CHARACTER SET utf8mb4";
		$parser = new PHPSQLParser();
		$parser->parse( $sql );
		$creator = new PHPSQLCreator();
		$created = $creator->create( $parser->parsed );
		$this->assertSame( $sql, $created, 'Creating a CREATE statement with multi column index' );
	}
}

