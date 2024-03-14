<?php

namespace PHPSQLParser\builders;

class AlterStatementBuilder implements Builder
{


    private function buildAlter($parsed)
    {
        $builder = new AlterBuilder();
        return $builder->build($parsed);
    }

    private function buildTable($parsed)
    {
        $builder = new CreateTableBuilder();
        return $builder->build($parsed);
    }

    private function buildAdd($parsed)
    {
        return (new AddBuilder())->build($parsed);
    }

    public function build(array $parsed)
    {
        $sql[] = $this->buildAlter($parsed['ALTER']);

        if (isset($parsed['TABLE'])) {
            $sql[] = $this->buildTable($parsed['TABLE']);
        }

        if (isset($parsed['ADD'])) {
            $sql[] = $this->buildAdd($parsed['ADD']);
        }

        return implode(" ", $sql);
    }
}
