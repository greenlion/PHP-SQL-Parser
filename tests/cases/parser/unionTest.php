<?php
/**
 * unionTest.php
 *
 * Test case for PHPSQLParser.
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
namespace PHPSQLParser\Test\Parser;
use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;
use Analog\Analog;

class UnionTest extends \PHPUnit_Framework_TestCase {
	
    public function testUnion1() {
        $parser = new PHPSQLParser();

        $sql = 'SELECT colA From test a
        union
        SELECT colB from test 
        as b';
        $p = $parser->parse($sql, true);
        Analog::log(serialize($p));
        $expected = getExpectedValue(dirname(__FILE__), 'union1.serialized');
        $this->assertEquals($expected, $p, 'simple union');
    }
    
    public function testUnion2() {
        // TODO: the order-by clause has not been parsed
    	$parser = new PHPSQLParser();
    	$sql = '(SELECT colA From test a)
                union all
                (SELECT colB from test b) order by 1';
        $p = $parser->parse($sql, true);
        $expected = getExpectedValue(dirname(__FILE__), 'union2.serialized');
        $this->assertEquals($expected, $p, 'mysql union with order-by');
    }

    public function testUnion3() {
        $sql = "SELECT COUNT(DISTINCT(peptide)) AS unique_peptide, COUNT(*) AS total_peptide, AVG(peptide_score1) AS avg_peptide_score1, reference AS reference, search_id, gene_symbol AS gene_symbol  FROM ((SELECT  sch.search_id as search_id, h.peptide_id AS peptide_id,h.ms2_id AS ms2_id,  42 AS search_index , m.ms2_first_scan_number AS scanf, m.ms2_charge AS charge, h.peptide_ppm AS ppm, h.peptide_score1 AS peptide_score1, h.peptide_score2 AS peptide_score2, concat(h.peptide_ions_matched ,'/', h.peptide_ions_total) AS ions, h.peptide_reference AS reference, h.peptide_number_redundancies AS redu, h.peptide_sequence AS peptide, '' as gene_symbol, '' as annotation FROM  (  searches AS sch, run_000042_search_000042_perscan AS s, run_000042_ms2_000042 AS m, run_000042_search_000042_perhit AS h )  WHERE (  sch.search_id = 42 AND h.ms2_id = s.ms2_id AND s.ms2_id = m.ms2_id ) AND (h.peptide_validity & 2) ) UNION ALL (SELECT  sch.search_id as search_id, h.peptide_id AS peptide_id,h.ms2_id AS ms2_id,  41 AS search_index , m.ms2_first_scan_number AS scanf, m.ms2_charge AS charge, h.peptide_ppm AS ppm, h.peptide_score1 AS peptide_score1, h.peptide_score2 AS peptide_score2, concat(h.peptide_ions_matched ,'/', h.peptide_ions_total) AS ions, h.peptide_reference AS reference, h.peptide_number_redundancies AS redu, h.peptide_sequence AS peptide, '' as annotation, '' as gene_symbol FROM  (  searches AS sch, run_000041_search_000041_perscan AS s, run_000041_ms2_000041 AS m, run_000041_search_000041_perhit AS h )  WHERE (  sch.search_id = 41 AND h.ms2_id = s.ms2_id AND s.ms2_id = m.ms2_id ) AND (h.peptide_validity & 2) ))  AS protein_count_final GROUP BY reference  ORDER BY unique_peptide DESC, total_peptide DESC, avg_peptide_score1 DESC ";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);
        $expected = getExpectedValue(dirname(__FILE__), 'union3.serialized');
        $this->assertEquals($expected, $p, 'complicated mysql union');
    }
}
?>
