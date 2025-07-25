<?php

declare(strict_types=1);

namespace Efabrica\PHPStanLatte\LatteContext\Collector;

use Efabrica\PHPStanLatte\LatteContext\CollectedData\CollectedMethod;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ExecutionEndNode;

/**
 * @extends AbstractLatteContextCollector<CollectedMethod>
 */
final class MethodTerminatingCollector extends AbstractLatteContextCollector
{
    public function getNodeTypes(): array
    {
        return [ExecutionEndNode::class];
    }

    /**
     * @param ExecutionEndNode $node
     * @phpstan-return null|CollectedMethod[]
     */
    public function collectData(Node $node, Scope $scope): ?array
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return null;
        }
        $actualClassName = $classReflection->getName();

        $methodName = $scope->getFunctionName();
        if ($methodName === null) {
            return null;
        }

        return [new CollectedMethod(
            $actualClassName,
            $methodName,
            $node->getStatementResult()->isAlwaysTerminating()
        )];
    }
}
