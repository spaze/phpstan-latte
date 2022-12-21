<?php

declare(strict_types=1);

namespace Efabrica\PHPStanLatte\Collector\Finder;

use Efabrica\PHPStanLatte\Collector\CollectedData\CollectedResolvedNode;
use Efabrica\PHPStanLatte\Collector\Collector\ResolvedNodeCollector;
use PHPStan\Node\CollectedDataNode;

/**
 * @phpstan-import-type CollectedResolvedNodeArray from CollectedResolvedNode
 */
final class ResolvedNodeFinder
{
    /**
     * @var array<string, CollectedResolvedNode[]>
     */
    private array $collectedResolvedNodes = [];

    public function __construct(CollectedDataNode $collectedDataNode)
    {
        $collectedResolvedNodes = ResolvedNodeCollector::loadData($collectedDataNode, CollectedResolvedNode::class);
        foreach ($collectedResolvedNodes as $collectedResolvedNode) {
            $resolver = $collectedResolvedNode->getResolver();
            if (!isset($this->collectedResolvedNodes[$resolver])) {
                $this->collectedResolvedNodes[$resolver] = [];
            }
            $this->collectedResolvedNodes[$resolver][] = $collectedResolvedNode;
        }
    }

    /**
     * @return CollectedResolvedNode[]
     */
    public function find(string $resolver): array
    {
        return $this->collectedResolvedNodes[$resolver] ?? [];
    }
}
