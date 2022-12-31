<?php

declare(strict_types=1);

namespace Efabrica\PHPStanLatte\LatteContext\Finder;

use Efabrica\PHPStanLatte\Analyser\LatteContextData;
use Efabrica\PHPStanLatte\LatteContext\CollectedData\CollectedComponent;
use Efabrica\PHPStanLatte\Template\Component;
use PHPStan\BetterReflection\BetterReflection;

final class ComponentFinder
{
    /**
     * @var array<string, array<string, Component[]>>
     */
    private array $collectedComponents = [];

    private MethodCallFinder $methodCallFinder;

    public function __construct(LatteContextData $latteContext, MethodCallFinder $methodCallFinder)
    {
        $this->methodCallFinder = $methodCallFinder;

        $componentsWithTypes = [];
        $collectedComponents = $latteContext->getCollectedData(CollectedComponent::class);
        foreach ($collectedComponents as $collectedComponent) {
            $className = $collectedComponent->getClassName();
            $methodName = $collectedComponent->getMethodName();
            if (!isset($this->collectedComponents[$className][$methodName])) {
                $this->collectedComponents[$className][$methodName] = [];
            }
            if (!isset($componentsWithTypes[$collectedComponent->getComponent()->getTypeAsString()])) {
                $componentsWithTypes[$collectedComponent->getComponent()->getTypeAsString()] = [];
            }
            $componentsWithTypes[$collectedComponent->getComponent()->getTypeAsString()][] = $collectedComponent->getComponent();

            $this->collectedComponents[$className][$methodName][] = $collectedComponent->getComponent();
        }

        foreach ($componentsWithTypes as $componentType => $components) {
            $subcomponents = array_merge($this->collectedComponents[$componentType][''] ?? [], $this->collectedComponents[$componentType]['__construct'] ?? []);
            foreach ($components as $component) {
                $component->setSubcomponents($subcomponents);
            }
        }
    }

    /**
     * @return Component[]
     */
    public function find(string $className, string $methodName): array
    {
        return array_merge(
            $this->collectedComponents[$className][''] ?? [],
            $this->findInParents($className),
            $this->findInMethodCalls($className, '__construct'),
            $this->findInMethodCalls($className, $methodName),
        );
    }

    /**
     * @return Component[]
     */
    private function findInParents(string $className)
    {
        $classReflection = (new BetterReflection())->reflector()->reflectClass($className);

        $collectedComponents = [];
        foreach ($classReflection->getParentClassNames() as $parentClass) {
            $collectedComponents = array_merge(
                $this->collectedComponents[$parentClass][''] ?? [],
                $collectedComponents
            );
        }
        return $collectedComponents;
    }

    /**
     * @param array<string, array<string, true>> $alreadyFound
     * @return Component[]
     */
    private function findInMethodCalls(string $className, string $methodName, array &$alreadyFound = []): array
    {
        $declaringClass = $this->methodCallFinder->getDeclaringClass($className, $methodName);
        if (!$declaringClass) {
            return [];
        }

        if (isset($alreadyFound[$declaringClass][$methodName])) {
            return []; // stop recursion
        } else {
            $alreadyFound[$declaringClass][$methodName] = true;
        }

        $collectedComponents = [
            $this->collectedComponents[$declaringClass][$methodName] ?? [],
        ];

        $methodCalls = $this->methodCallFinder->findCalled($className, $methodName);
        foreach ($methodCalls as $calledMethod) {
            $collectedComponents[] = $this->findInMethodCalls($calledMethod->getCalledClassName(), $calledMethod->getCalledMethodName(), $alreadyFound);
        }

        return array_merge(...$collectedComponents);
    }
}
