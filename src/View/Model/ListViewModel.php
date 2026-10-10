<?php

namespace Fedale\CalendarBundle\View\Model;

final readonly class ListViewModel
{
    /**
     * @param list<ListDay> $days only the days with at least one entry
     */
    public function __construct(public array $days)
    {
    }
}
