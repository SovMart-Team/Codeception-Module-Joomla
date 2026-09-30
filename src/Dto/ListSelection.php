<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class ListSelection
{
    public int $boxchecked;

    /** @param list<int|string|FixtureReference> $ids */
    public function __construct(public array $ids, ?int $boxchecked = null)
    {
        $this->boxchecked = $boxchecked ?? count($ids);
    }

    /** @param list<int|string> $resolvedIds @return array{cid: list<int|string>, boxchecked: int} */
    public function fields(array $resolvedIds): array
    {
        if (!array_is_list($resolvedIds) || count($resolvedIds) !== $this->boxchecked) {
            throw new \InvalidArgumentException('Resolved Joomla list selection must match boxchecked.');
        }

        foreach ($resolvedIds as $id) {
            if ((is_int($id) && $id > 0) || (is_string($id) && trim($id) !== '')) {
                continue;
            }

            throw new \InvalidArgumentException('Resolved Joomla list selection contains an invalid id.');
        }

        return [
            'cid'        => $resolvedIds,
            'boxchecked' => $this->boxchecked,
        ];
    }
}
