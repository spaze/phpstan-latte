<?php

declare(strict_types=1);

namespace Efabrica\PHPStanLatte\LatteContext\CollectedData;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;

final class CollectedMethodCall extends CollectedLatteContextObject
{
    public const CALL = 'call';
    public const TERMINATING_CALL = 'terminating';
    public const OUTPUT_CALL = 'output';

    private string $callerClassName;

    private string $callerMethodName;

    private string $calledClassName;

    private string $calledMethodName;

    private bool $isCalledConditionally;

    private string $type;

    /** @var array<string, string|int|float|bool> */
    private array $params;

    /**
     * @param array<string, string|int|float|bool> $params
     */
    public function __construct(
        string $callerClassName,
        string $callerMethodName,
        string $calledClassName,
        string $calledMethodName,
        bool $isCalledConditionally,
        string $type = self::CALL,
        array $params = []
    ) {
        $this->callerClassName = $callerClassName;
        $this->callerMethodName = $callerMethodName;
        $this->calledClassName = $calledClassName;
        $this->calledMethodName = $calledMethodName;
        $this->isCalledConditionally = $isCalledConditionally;
        $this->type = $type;
        $this->params = $params;
    }

    public function getCallerClassName(): string
    {
        return $this->callerClassName;
    }

    public function getCallerMethodName(): string
    {
        return $this->callerMethodName;
    }

    public function getCalledClassName(): string
    {
        return $this->calledClassName;
    }

    public function getCalledMethodName(): string
    {
        return $this->calledMethodName;
    }

    public function isCalledConditionally(): bool
    {
        return $this->isCalledConditionally;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isCall(): bool
    {
        return $this->type === self::CALL;
    }

    public function isTerminatingCall(): bool
    {
        return $this->type === self::TERMINATING_CALL;
    }

    public function isOutputCall(): bool
    {
        return $this->type === self::OUTPUT_CALL;
    }

    /**
     * @return array<string, string|int|float|bool>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param array<string, string|int|float|bool> $params
     */
    public static function build(
        Node $node,
        Scope $scope,
        string $calledClassName,
        string $calledMethodName,
        string $type = self::CALL,
        array $params = []
    ): self {
        /** @var Node $parentNode */
        $parentNode = $node->getAttribute('parent') ?? $node;
        /** @var Node $parentNode */
        $grandparentNode = $parentNode->getAttribute('parent') ?? $parentNode;
        return new self(
            $scope->getClassReflection() !== null ? $scope->getClassReflection()->getName() : '',
            $node instanceof ClassMethod ? $node->name->name : $scope->getFunctionName() ?? '',
            $calledClassName,
            $calledMethodName,
            !$grandparentNode instanceof ClassMethod,
            $type,
            $params
        );
    }
}
