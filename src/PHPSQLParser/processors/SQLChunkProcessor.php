<?php
/**
 * SQLChunkProcessor.php
 *
 * This file implements the processor for the SQL chunks.
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

namespace PHPSQLParser\processors;

/**
 * This class processes the SQL chunks.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class SQLChunkProcessor extends AbstractProcessor {

    protected function moveLIKE(&$out) {
        if (!isset($out['TABLE']['like'])) {
            return;
        }
        $out = $this->array_insert_after($out, 'TABLE', array('LIKE' => $out['TABLE']['like']));
        unset($out['TABLE']['like']);
    }

    public function process($out) {
        if (!$out) {
            return false;
        }
        if (!empty($out['BRACKET'])) {
            // TODO: this field should be a global STATEMENT field within the output
            // we could add all other categories as sub_tree, it could also work with multipe UNIONs
            $processor = new BracketProcessor();
            $out['BRACKET'] = $processor->process($out['BRACKET']);
        }
        if (!empty($out['CREATE'])) {
            $processor = new CreateProcessor();
            $out['CREATE'] = $processor->process($out['CREATE']);
        }
        if (!empty($out['TABLE'])) {
            $processor = new TableProcessor();
            $out['TABLE'] = $processor->process($out['TABLE']);
            $this->moveLIKE($out);
        }
        if (!empty($out['INDEX'])) {
            $processor = new IndexProcessor();
            $out['INDEX'] = $processor->process($out['INDEX']);
        }
        if (!empty($out['EXPLAIN'])) {
            $processor = new ExplainProcessor();
            $out['EXPLAIN'] = $processor->process($out['EXPLAIN'], array_keys($out));
        }
        if (!empty($out['DESCRIBE'])) {
            $processor = new DescribeProcessor();
            $out['DESCRIBE'] = $processor->process($out['DESCRIBE'], array_keys($out));
        }
        if (!empty($out['DESC'])) {
            $processor = new DescProcessor();
            $out['DESC'] = $processor->process($out['DESC'], array_keys($out));
        }
        if (!empty($out['SELECT'])) {
            $processor = new SelectProcessor();
            $out['SELECT'] = $processor->process($out['SELECT']);
        }
        if (!empty($out['FROM'])) {
            $processor = new FromProcessor();
            $out['FROM'] = $processor->process($out['FROM']);
        }
        if (!empty($out['USING'])) {
            $processor = new UsingProcessor();
            $out['USING'] = $processor->process($out['USING']);
        }
        if (!empty($out['UPDATE'])) {
            $processor = new UpdateProcessor();
            $out['UPDATE'] = $processor->process($out['UPDATE']);
        }
        if (!empty($out['GROUP'])) {
            // set empty array if we have partial SQL statement
            $processor = new GroupByProcessor();
            $out['GROUP'] = $processor->process($out['GROUP'], isset($out['SELECT']) ? $out['SELECT'] : array());
        }
        if (!empty($out['ORDER'])) {
            // set empty array if we have partial SQL statement
            $processor = new OrderByProcessor();
            $out['ORDER'] = $processor->process($out['ORDER'], isset($out['SELECT']) ? $out['SELECT'] : array());
        }
        if (!empty($out['LIMIT'])) {
            $processor = new LimitProcessor();
            $out['LIMIT'] = $processor->process($out['LIMIT']);
        }
        if (!empty($out['WHERE'])) {
            $processor = new WhereProcessor();
            $out['WHERE'] = $processor->process($out['WHERE']);
        }
        if (!empty($out['HAVING'])) {
            $processor = new HavingProcessor();
            $out['HAVING'] = $processor->process($out['HAVING'], isset($out['SELECT']) ? $out['SELECT'] : array());
        }
        if (!empty($out['SET'])) {
            $processor = new SetProcessor();
            $out['SET'] = $processor->process($out['SET'], isset($out['UPDATE']));
        }
        if (!empty($out['DUPLICATE'])) {
            $processor = new DuplicateProcessor();
            $out['ON DUPLICATE KEY UPDATE'] = $processor->process($out['DUPLICATE']);
            unset($out['DUPLICATE']);
        }
        if (!empty($out['INSERT'])) {
            $processor = new InsertProcessor();
            $out = $processor->process($out);
        }
        if (!empty($out['REPLACE'])) {
            $processor = new ReplaceProcessor();
            $out = $processor->process($out);
        }
        if (!empty($out['DELETE'])) {
            $processor = new DeleteProcessor();
            $out = $processor->process($out);
        }
        if (!empty($out['VALUES'])) {
            $processor = new ValuesProcessor();
            $out = $processor->process($out);
        }
        if (!empty($out['INTO'])) {
            $processor = new IntoProcessor();
            $out = $processor->process($out);
        }
        if (!empty($out['DROP'])) {
            $processor = new DropProcessor();
            $out['DROP'] = $processor->process($out['DROP']);
        }
        if (!empty($out['RENAME'])) {
            $processor = new RenameProcessor();
            $out['RENAME'] = $processor->process($out['RENAME']);
        }
        if (!empty($out['SHOW'])) {
            $processor = new ShowProcessor();
            $out['SHOW'] = $processor->process($out['SHOW']);
        }
        if (!empty($out['OPTIONS'])) {
            $processor = new OptionsProcessor();
            $out['OPTIONS'] = $processor->process($out['OPTIONS']);
        }
        if (!empty($out['WITH'])) {
        	$processor = new WithProcessor();
        	$out['WITH'] = $processor->process($out['WITH']);
        }
        return $out;
    }
}
?>
