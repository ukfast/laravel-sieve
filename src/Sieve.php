<?php

namespace UKFast\Sieve;

use Illuminate\Http\Request;

class Sieve
{
    protected $filters = [];

    protected $request;

    protected $defaultSort;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function configure($callback)
    {
        foreach ($callback(new FilterBuilder) as $prop => $filter) {
            $this->addFilter($prop, $filter);
        }
    }

    public function addFilter($property, $filter)
    {
        $this->filters[] = compact('property', 'filter');
    }

    public function filters()
    {
        return new FilterBuilder;
    }

    public function getFilters()
    {
        return $this->filters;
    }

    public function apply($queryBuilder)
    {
        foreach ($this->getFilters() as $sieveFilter) {
            /** @var Filter */
            $filter = $sieveFilter['filter'];
            $property = $sieveFilter['property'];

            $sort = $this->request->get("sort");

            foreach ($filter->operators() as $operator) {
                $eqFilter = $operator == 'eq' && $this->request->has($property);
                if (!$this->request->has("$property:$operator") && !$eqFilter) {
                    continue;
                }

                $term = $this->request->get("$property:$operator");
                if ($eqFilter) {
                    $term = $this->request->get($property);
                }

                $search = new SearchTerm($property, $operator, $property, $term);
                $filter->modifyQuery($queryBuilder, $search);
            }

            if ($this->getSort() == "$property:desc") {
                $queryBuilder->orderBy($property, "desc");
            }

            if ($this->getSort() == "$property:asc") {
                $queryBuilder->orderBy($property, "asc");
            }
        }

        return $this;
    }

    public function setDefaultSort($property='id', $direction='asc'): Sieve
    {
        $this->defaultSort = $property.':'.$direction;
        return $this;
    }

    public function getSort(): ?string
    {
        return $this->request->get("sort") ?? $this->defaultSort;
    }
}
