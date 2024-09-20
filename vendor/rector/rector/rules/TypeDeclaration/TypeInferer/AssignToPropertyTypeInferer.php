<?php

declare (strict_types=1);
namespace Rector\TypeDeclaration\TypeInferer;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PHPStan\Type\ArrayType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use Rector\NodeAnalyzer\ExprAnalyzer;
use Rector\NodeAnalyzer\PropertyFetchAnalyzer;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\NodeTypeResolver\PHPStan\Type\TypeFactory;
use Rector\PhpDocParser\NodeTraverser\SimpleCallableNodeTraverser;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\TypeDeclaration\AlreadyAssignDetector\ConstructorAssignDetector;
use Rector\TypeDeclaration\AlreadyAssignDetector\NullTypeAssignDetector;
use Rector\TypeDeclaration\AlreadyAssignDetector\PropertyDefaultAssignDetector;
use Rector\TypeDeclaration\Matcher\PropertyAssignMatcher;
/**
 * @internal
 */
final class AssignToPropertyTypeInferer
{
    /**
     * @readonly
     * @var \Rector\TypeDeclaration\AlreadyAssignDetector\ConstructorAssignDetector
     */
    private $constructorAssignDetector;
    /**
     * @readonly
     * @var \Rector\TypeDeclaration\Matcher\PropertyAssignMatcher
     */
    private $propertyAssignMatcher;
    /**
     * @readonly
     * @var \Rector\TypeDeclaration\AlreadyAssignDetector\PropertyDefaultAssignDetector
     */
    private $propertyDefaultAssignDetector;
    /**
     * @readonly
     * @var \Rector\TypeDeclaration\AlreadyAssignDetector\NullTypeAssignDetector
     */
    private $nullTypeAssignDetector;
    /**
     * @readonly
     * @var \Rector\PhpDocParser\NodeTraverser\SimpleCallableNodeTraverser
     */
    private $simpleCallableNodeTraverser;
    /**
     * @readonly
     * @var \Rector\NodeTypeResolver\PHPStan\Type\TypeFactory
     */
    private $typeFactory;
    /**
     * @readonly
     * @var \Rector\NodeTypeResolver\NodeTypeResolver
     */
    private $nodeTypeResolver;
    /**
     * @readonly
     * @var \Rector\NodeAnalyzer\ExprAnalyzer
     */
    private $exprAnalyzer;
    /**
     * @readonly
     * @var \Rector\PhpParser\Node\Value\ValueResolver
     */
    private $valueResolver;
    /**
     * @readonly
     * @var \Rector\NodeAnalyzer\PropertyFetchAnalyzer
     */
    private $propertyFetchAnalyzer;
    public function __construct(ConstructorAssignDetector $constructorAssignDetector, PropertyAssignMatcher $propertyAssignMatcher, PropertyDefaultAssignDetector $propertyDefaultAssignDetector, NullTypeAssignDetector $nullTypeAssignDetector, SimpleCallableNodeTraverser $simpleCallableNodeTraverser, TypeFactory $typeFactory, NodeTypeResolver $nodeTypeResolver, ExprAnalyzer $exprAnalyzer, ValueResolver $valueResolver, PropertyFetchAnalyzer $propertyFetchAnalyzer)
    {
        $this->constructorAssignDetector = $constructorAssignDetector;
        $this->propertyAssignMatcher = $propertyAssignMatcher;
        $this->propertyDefaultAssignDetector = $propertyDefaultAssignDetector;
        $this->nullTypeAssignDetector = $nullTypeAssignDetector;
        $this->simpleCallableNodeTraverser = $simpleCallableNodeTraverser;
        $this->typeFactory = $typeFactory;
        $this->nodeTypeResolver = $nodeTypeResolver;
        $this->exprAnalyzer = $exprAnalyzer;
        $this->valueResolver = $valueResolver;
        $this->propertyFetchAnalyzer = $propertyFetchAnalyzer;
    }
    public function inferPropertyInClassLike(Property $property, string $propertyName, ClassLike $classLike) : ?Type
    {
        if ($this->hasAssignDynamicPropertyValue($classLike, $propertyName)) {
            return null;
        }
        $assignedExprTypes = $this->getAssignedExprTypes($classLike, $propertyName);
        if ($this->shouldAddNullType($classLike, $propertyName, $assignedExprTypes)) {
            $assignedExprTypes[] = new NullType();
        }
        return $this->resolveTypeWithVerifyDefaultValue($property, $assignedExprTypes);
    }
    /**
     * @param Type[] $assignedExprTypes
     */
    private function resolveTypeWithVerifyDefaultValue(Property $property, array $assignedExprTypes) : ?Type
    {
        $defaultPropertyValue = $property->props[0]->default;
        if ($assignedExprTypes === []) {
            // not typed, never assigned, but has default value, then pull type from default value
            if (!$property->type instanceof Node && $defaultPropertyValue instanceof Expr) {
                return $this->nodeTypeResolver->getType($defaultPropertyValue);
            }
            return null;
        }
        $inferredType = $this->typeFactory->createMixedPassedOrUnionType($assignedExprTypes);
        if ($this->shouldSkipWithDifferentDefaultValueType($defaultPropertyValue, $inferredType)) {
            return null;
        }
        return $inferredType;
    }
    private function shouldSkipWithDifferentDefaultValueType(?Expr $expr, Type $inferredType) : bool
    {
        if (!$expr instanceof Expr) {
            return \false;
        }
        if ($this->valueResolver->isNull($expr)) {
            return \false;
        }
        $defaultType = $this->nodeTypeResolver->getNativeType($expr);
        return $inferredType->isSuperTypeOf($defaultType)->no();
    }
    private function resolveExprStaticTypeIncludingDimFetch(Assign $assign) : Type
    {
        $exprStaticType = $this->nodeTypeResolver->getNativeType($assign->expr);
        if ($assign->var instanceof ArrayDimFetch) {
            return new ArrayType(new MixedType(), $exprStaticType);
        }
        return $exprStaticType;
    }
    /**
     * @param Type[] $assignedExprTypes
     */
    private function shouldAddNullType(ClassLike $classLike, string $propertyName, array $assignedExprTypes) : bool
    {
        $hasPropertyDefaultValue = $this->propertyDefaultAssignDetector->detect($classLike, $propertyName);
        $isAssignedInConstructor = $this->constructorAssignDetector->isPropertyAssigned($classLike, $propertyName);
        if ($assignedExprTypes === [] && ($isAssignedInConstructor || $hasPropertyDefaultValue)) {
            return \false;
        }
        $shouldAddNullType = $this->nullTypeAssignDetector->detect($classLike, $propertyName);
        if ($shouldAddNullType) {
            if ($isAssignedInConstructor) {
                return \false;
            }
            return !$hasPropertyDefaultValue;
        }
        if ($assignedExprTypes === []) {
            return \false;
        }
        if ($isAssignedInConstructor) {
            return \false;
        }
        return !$hasPropertyDefaultValue;
    }
    private function hasAssignDynamicPropertyValue(ClassLike $classLike, string $propertyName) : bool
    {
        $hasAssignDynamicPropertyValue = \false;
        $this->simpleCallableNodeTraverser->traverseNodesWithCallable($classLike->stmts, function (Node $node) use($propertyName, &$hasAssignDynamicPropertyValue) : ?int {
            if (!$node instanceof Assign) {
                return null;
            }
            $expr = $this->propertyAssignMatcher->matchPropertyAssignExpr($node, $propertyName);
            if (!$expr instanceof Expr) {
                if (!$this->propertyFetchAnalyzer->isLocalPropertyFetch($node->var)) {
                    return null;
                }
                /** @var PropertyFetch|StaticPropertyFetch $assignVar */
                $assignVar = $node->var;
                if (!$assignVar->name instanceof Identifier) {
                    $hasAssignDynamicPropertyValue = \true;
                    return NodeTraverser::STOP_TRAVERSAL;
                }
                return null;
            }
            return null;
        });
        return $hasAssignDynamicPropertyValue;
    }
    /**
     * @return array<Type>
     */
    private function getAssignedExprTypes(ClassLike $classLike, string $propertyName) : array
    {
        $assignedExprTypes = [];
        $this->simpleCallableNodeTraverser->traverseNodesWithCallable($classLike->stmts, function (Node $node) use($propertyName, &$assignedExprTypes) : ?int {
            if (!$node instanceof Assign) {
                return null;
            }
            $expr = $this->propertyAssignMatcher->matchPropertyAssignExpr($node, $propertyName);
            if (!$expr instanceof Expr) {
                return null;
            }
            if ($this->exprAnalyzer->isNonTypedFromParam($node->expr)) {
                $assignedExprTypes = [];
                return NodeTraverser::STOP_TRAVERSAL;
            }
            $assignedExprTypes[] = $this->resolveExprStaticTypeIncludingDimFetch($node);
            return null;
        });
        return $assignedExprTypes;
    }
}
