<?php

namespace Fedale\CalendarBundle\View;

use Fedale\CalendarBundle\Model\CalendarResource;

/**
 * Resources as a tree: roots (no parent, or a parent that is not among the
 * resources) and children by parent id, both sorted by order then title.
 */
final readonly class ResourceTree
{
    /**
     * @param list<CalendarResource>                $roots
     * @param array<string, list<CalendarResource>> $children
     */
    private function __construct(
        public array $roots,
        public array $children,
    ) {
    }

    /**
     * @param list<CalendarResource> $resources
     */
    public static function of(array $resources): self
    {
        usort($resources, fn(CalendarResource $a, CalendarResource $b) => [$a->order, $a->title] <=> [$b->order, $b->title]);
        $ids = array_flip(array_map(fn(CalendarResource $resource) => $resource->id, $resources));

        $roots = [];
        $children = [];
        foreach ($resources as $resource) {
            if ($resource->parentId !== null && $resource->parentId !== $resource->id && isset($ids[$resource->parentId])) {
                $children[$resource->parentId][] = $resource;
            } else {
                $roots[] = $resource;
            }
        }

        return new self($roots, $children);
    }

    public function hasChildren(CalendarResource $resource): bool
    {
        return isset($this->children[$resource->id]);
    }

    /**
     * Depth-first: every parent followed by its children.
     *
     * @return list<CalendarResource>
     */
    public function flatten(): array
    {
        $flat = [];
        $walk = function (array $resources) use (&$walk, &$flat): void {
            foreach ($resources as $resource) {
                $flat[] = $resource;
                $walk($this->children[$resource->id] ?? []);
            }
        };
        $walk($this->roots);

        return $flat;
    }
}
