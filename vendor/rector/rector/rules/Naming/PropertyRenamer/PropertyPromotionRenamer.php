<?php

declare (strict_types=1);
namespace Rector\Naming\PropertyRenamer;

use PhpParser\Node\Expr\Error;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Naming\ExpectedNameResolver\MatchParamTypeExpectedNameResolver;
use Rector\Naming\ParamRenamer\ParamRenamer;
use Rector\Naming\ValueObject\ParamRename;
use Rector\Naming\ValueObjectFactory\ParamRenameFactory;
use Rector\Naming\VariableRenamer;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\Php\PhpVersionProvider;
use Rector\ValueObject\MethodName;
use Rector\ValueObject\PhpVersionFeature;
final class PropertyPromotionRenamer
{
    /**
     * @readonly
     * @var \Rector\Php\PhpVersionProvider
     */
    private $phpVersionProvider;
    /**
     * @readonly
     * @var \Rector\Naming\ExpectedNameResolver\MatchParamTypeExpectedNameResolver
     */
    private $matchParamTypeExpectedNameResolver;
    /**
     * @readonly
     * @var \Rector\Naming\ValueObjectFactory\ParamRenameFactory
     */
    private $paramRenameFactory;
    /**
     * @readonly
     * @var \Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory
     */
    private $phpDocInfoFactory;
    /**
     * @readonly
     * @var \Rector\Naming\ParamRenamer\ParamRenamer
     */
    private $paramRenamer;
    /**
     * @readonly
     * @var \Rector\Naming\PropertyRenamer\PropertyFetchRenamer
     */
    private $propertyFetchRenamer;
    /**
     * @readonly
     * @var \Rector\NodeNameResolver\NodeNameResolver
     */
    private $nodeNameResolver;
    /**
     * @readonly
     * @var \Rector\Naming\VariableRenamer
     */
    private $variableRenamer;
    /**
     * @readonly
     * @var \Rector\Comments\NodeDocBlock\DocBlockUpdater
     */
    private $docBlockUpdater;
    public function __construct(PhpVersionProvider $phpVersionProvider, MatchParamTypeExpectedNameResolver $matchParamTypeExpectedNameResolver, ParamRenameFactory $paramRenameFactory, PhpDocInfoFactory $phpDocInfoFactory, ParamRenamer $paramRenamer, \Rector\Naming\PropertyRenamer\PropertyFetchRenamer $propertyFetchRenamer, NodeNameResolver $nodeNameResolver, VariableRenamer $variableRenamer, DocBlockUpdater $docBlockUpdater)
    {
        $this->phpVersionProvider = $phpVersionProvider;
        $this->matchParamTypeExpectedNameResolver = $matchParamTypeExpectedNameResolver;
        $this->paramRenameFactory = $paramRenameFactory;
        $this->phpDocInfoFactory = $phpDocInfoFactory;
        $this->paramRenamer = $paramRenamer;
        $this->propertyFetchRenamer = $propertyFetchRenamer;
        $this->nodeNameResolver = $nodeNameResolver;
        $this->variableRenamer = $variableRenamer;
        $this->docBlockUpdater = $docBlockUpdater;
    }
    /**
     * @param \PhpParser\Node\Stmt\Class_|\PhpParser\Node\Stmt\Interface_ $classLike
     */
    public function renamePropertyPromotion($classLike) : bool
    {
        $hasChanged = \false;
        if (!$this->phpVersionProvider->isAtLeastPhpVersion(PhpVersionFeature::PROPERTY_PROMOTION)) {
            return \false;
        }
        $constructClassMethod = $classLike->getMethod(MethodName::CONSTRUCT);
        if (!$constructClassMethod instanceof ClassMethod) {
            return \false;
        }
        // resolve possible and existing param names
        $blockingParamNames = $this->resolveBlockingParamNames($constructClassMethod);
        foreach ($constructClassMethod->params as $param) {
            if ($param->flags === 0) {
                continue;
            }
            // promoted property
            $desiredPropertyName = $this->matchParamTypeExpectedNameResolver->resolve($param);
            if ($desiredPropertyName === null) {
                continue;
            }
            if (\in_array($desiredPropertyName, $blockingParamNames, \true)) {
                continue;
            }
            $currentParamName = $this->nodeNameResolver->getName($param);
            if ($this->isNameSuffixed($currentParamName, $desiredPropertyName)) {
                continue;
            }
            $this->renameParamVarNameAndVariableUsage($classLike, $constructClassMethod, $desiredPropertyName, $param);
            $hasChanged = \true;
        }
        return $hasChanged;
    }
    public function renameParamDoc(PhpDocInfo $phpDocInfo, ClassMethod $classMethod, Param $param, string $paramVarName, string $desiredPropertyName) : void
    {
        $paramTagValueNode = $phpDocInfo->getParamTagValueByName($paramVarName);
        if (!$paramTagValueNode instanceof ParamTagValueNode) {
            return;
        }
        $paramRename = $this->paramRenameFactory->createFromResolvedExpectedName($classMethod, $param, $desiredPropertyName);
        if (!$paramRename instanceof ParamRename) {
            return;
        }
        $this->paramRenamer->rename($paramRename);
        $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($classMethod);
    }
    private function renameParamVarNameAndVariableUsage(ClassLike $classLike, ClassMethod $classMethod, string $desiredPropertyName, Param $param) : void
    {
        if ($param->var instanceof Error) {
            return;
        }
        $classMethodPhpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($classMethod);
        $currentParamName = $this->nodeNameResolver->getName($param);
        $this->propertyFetchRenamer->renamePropertyFetchesInClass($classLike, $currentParamName, $desiredPropertyName);
        /** @var string $paramVarName */
        $paramVarName = $param->var->name;
        $this->renameParamDoc($classMethodPhpDocInfo, $classMethod, $param, $paramVarName, $desiredPropertyName);
        $param->var = new Variable($desiredPropertyName);
        $this->variableRenamer->renameVariableInFunctionLike($classMethod, $paramVarName, $desiredPropertyName);
    }
    /**
     * Sometimes the bare type is not enough.
     * This allows prefixing type in variable names, e.g. "Type $firstType"
     */
    private function isNameSuffixed(string $currentParamName, string $desiredPropertyName) : bool
    {
        $currentNameLowercased = \strtolower($currentParamName);
        $expectedNameLowercased = \strtolower($desiredPropertyName);
        return \substr_compare($currentNameLowercased, $expectedNameLowercased, -\strlen($expectedNameLowercased)) === 0;
    }
    /**
     * @return int[]|string[]
     */
    private function resolveBlockingParamNames(ClassMethod $classMethod) : array
    {
        $futureParamNames = [];
        foreach ($classMethod->params as $param) {
            $futureParamName = $this->matchParamTypeExpectedNameResolver->resolve($param);
            if ($futureParamName === null) {
                continue;
            }
            $futureParamNames[] = $futureParamName;
        }
        // remove null values
        $futureParamNames = \array_filter($futureParamNames);
        if ($futureParamNames === []) {
            return [];
        }
        // resolve duplicated names
        $blockingParamNames = [];
        $valuesToCount = \array_count_values($futureParamNames);
        foreach ($valuesToCount as $value => $count) {
            if ($count < 2) {
                continue;
            }
            $blockingParamNames[] = $value;
        }
        return $blockingParamNames;
    }
}
