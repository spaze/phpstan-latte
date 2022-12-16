<?php

declare(strict_types=1);

namespace Efabrica\PHPStanLatte\Rule;

use Efabrica\PHPStanLatte\Analyser\AnalysedTemplatesRegistry;
use Efabrica\PHPStanLatte\Analyser\FileAnalyserFactory;
use Efabrica\PHPStanLatte\Collector\AdditionalDataCollector;
use Efabrica\PHPStanLatte\Collector\Finder\ResolvedNodeFinder;
use Efabrica\PHPStanLatte\Collector\ResolvedNodeCollector;
use Efabrica\PHPStanLatte\Collector\TemplateRenderCollector;
use Efabrica\PHPStanLatte\Collector\ValueObject\CollectedTemplateRender;
use Efabrica\PHPStanLatte\Compiler\LatteToPhpCompiler;
use Efabrica\PHPStanLatte\Error\ErrorBuilder;
use Efabrica\PHPStanLatte\LatteTemplateResolver\LatteTemplateResolverInterface;
use Efabrica\PHPStanLatte\Template\Template;
use Efabrica\PHPStanLatte\Type\TypeSerializer;
use PhpParser\Node;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Registry as CollectorsRegistry;
use PHPStan\File\RelativePathHelper;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Registry as RuleRegistry;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Throwable;

/**
 * @phpstan-import-type CollectedTemplateRenderArray from CollectedTemplateRender
 * @implements Rule<CollectedDataNode>
 */
final class LatteTemplatesRule implements Rule
{
    /** @var LatteTemplateResolverInterface[] */
    private array $latteTemplateResolvers;

    private LatteToPhpCompiler $latteToPhpCompiler;

    private FileAnalyserFactory $fileAnalyserFactory;

    private AnalysedTemplatesRegistry $analysedTemplatesRegistry;

    private RuleRegistry $rulesRegistry;

    private TemplateRenderCollector $templateRenderCollector;

    private ErrorBuilder $errorBuilder;

    private RelativePathHelper $relativePathHelper;

    private TypeSerializer $typeSerializer;

    private AdditionalDataCollector $additionalDataCollector;

    /**
     * @param LatteTemplateResolverInterface[] $latteTemplateResolvers
     */
    public function __construct(
        array $latteTemplateResolvers,
        LatteToPhpCompiler $latteToPhpCompiler,
        FileAnalyserFactory $fileAnalyserFactory,
        AnalysedTemplatesRegistry $analysedTemplatesRegistry,
        RuleRegistry $rulesRegistry,
        TemplateRenderCollector $templateRenderCollector,
        ErrorBuilder $errorBuilder,
        RelativePathHelper $relativePathHelper,
        TypeSerializer $typeSerializer,
        AdditionalDataCollector $additionalDataCollector
    ) {
        $this->latteTemplateResolvers = $latteTemplateResolvers;
        $this->latteToPhpCompiler = $latteToPhpCompiler;
        $this->fileAnalyserFactory = $fileAnalyserFactory;
        $this->analysedTemplatesRegistry = $analysedTemplatesRegistry;
        $this->rulesRegistry = $rulesRegistry;
        $this->templateRenderCollector = $templateRenderCollector;
        $this->errorBuilder = $errorBuilder;
        $this->relativePathHelper = $relativePathHelper;
        $this->typeSerializer = $typeSerializer;
        $this->additionalDataCollector = $additionalDataCollector;
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @param CollectedDataNode $collectedDataNode
     */
    public function processNode(Node $collectedDataNode, Scope $scope): array
    {
        $resolvedNodes = $collectedDataNode->get(ResolvedNodeCollector::class);
        $processedFiles = array_unique(array_keys($resolvedNodes));
        $collectedDataNode = $this->additionalDataCollector->collect($collectedDataNode, $processedFiles);
        $resolvedNodeFinder = new ResolvedNodeFinder($collectedDataNode, $this->typeSerializer);

        $errors = [];
        foreach ($this->latteTemplateResolvers as $latteTemplateResolver) {
            foreach ($resolvedNodeFinder->find(get_class($latteTemplateResolver)) as $collectedResolvedNode) {
                $result = $latteTemplateResolver->resolve($collectedResolvedNode, $collectedDataNode);
                $errors = array_merge($errors, $result->getErrors());
                $this->analyseTemplates($result->getTemplates(), $scope, $errors);
            }
        }

        foreach ($this->analysedTemplatesRegistry->getReportedUnanalysedTemplates() as $templatePath) {
            $errors[] = RuleErrorBuilder::message('Latte template ' . pathinfo($templatePath, PATHINFO_BASENAME) . ' was not analysed.')
                ->file($templatePath)
                ->build();
        }

        return $errors;
    }

    /**
     * @param Template[] $templates
     * @param RuleError[] $errors
     * @param array<string, int> $alreadyAnalysed
     */
    private function analyseTemplates(array $templates, Scope $scope, array &$errors, array &$alreadyAnalysed = []): void
    {
        foreach ($templates as $template) {
            $templatePath = $template->getPath();

            if (!array_key_exists($templatePath, $alreadyAnalysed)) {
                $alreadyAnalysed[$templatePath] = 1;
            } elseif ($alreadyAnalysed[$templatePath] <= 3) {
                $alreadyAnalysed[$templatePath]++;
            } else {
                continue; // stop recursion when template is analysed more than 3 times in include chain
            }

            $context = '';
            $actualClass = $template->getActualClass();
            if ($actualClass !== null) {
                $context .= $actualClass;
            }
            $actualAction = $template->getActualAction();
            if ($actualAction !== null) {
                $context .= '::' . $actualAction;
            }
            $parentTemplatePath = $template->getParentTemplatePath();
            if ($parentTemplatePath !== null) {
                $context .= ' included in ' . $this->relativePathHelper->getRelativePath($parentTemplatePath);
            }

            try {
                $compileFilePath = $this->latteToPhpCompiler->compileFile($template, $context);
                require($compileFilePath); // load type definitions from compiled template
            } catch (Throwable $e) {
                $errors = array_merge($errors, $this->errorBuilder->buildErrors([new Error($e->getMessage() ?: get_class($e), $scope->getFile())], $templatePath, $context));
                continue;
            }

            $fileAnalyserResult = $this->fileAnalyserFactory->create()->analyseFile(
                $compileFilePath,
                [],
                $this->rulesRegistry,
                new CollectorsRegistry([$this->templateRenderCollector]),
                null
            );
            $this->analysedTemplatesRegistry->templateAnalysed($templatePath);

            $errors = array_merge($errors, $this->errorBuilder->buildErrors($fileAnalyserResult->getErrors(), $templatePath, $context));

            $dir = dirname($templatePath);

            $includeTemplates = [];
            $collectedTemplateRenders = $this->templateRenderCollector->extractCollectedData($fileAnalyserResult->getCollectedData(), $this->typeSerializer, CollectedTemplateRender::class);
            foreach ($collectedTemplateRenders as $collectedTemplateRender) {
                $includedTemplatePath = $collectedTemplateRender->getTemplatePath();
                if (is_string($includedTemplatePath) && $includedTemplatePath !== '') {
                    if ($includedTemplatePath[0] !== '/') {
                        $includedTemplatePath = $dir . '/' . $includedTemplatePath;
                    }
                    $includedTemplatePath = realpath($includedTemplatePath) ?: $includedTemplatePath;
                    $includeTemplates[] = new Template(
                        $includedTemplatePath,
                        $actualClass,
                        $actualAction,
                        array_merge($collectedTemplateRender->getVariables(), $template->getVariables()),
                        $template->getComponents(),
                        $template->getForms(),
                        $template->getPath()
                    );
                } elseif ($includedTemplatePath === '') {
                    $errors[] = $this->errorBuilder->buildError(
                        new Error('Empty path to included latte template.', $collectedTemplateRender->getFile(), $collectedTemplateRender->getLine()),
                        $templatePath
                    );
                } elseif ($includedTemplatePath === false) {
                    $errors[] = $this->errorBuilder->buildError(
                        new Error('Cannot resolve included latte template.', $collectedTemplateRender->getFile(), $collectedTemplateRender->getLine()),
                        $templatePath
                    );
                }
            }
            $this->analyseTemplates($includeTemplates, $scope, $errors, $alreadyAnalysed);
        }
    }
}
