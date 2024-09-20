<?php

declare (strict_types=1);
namespace Rector\NodeTypeResolver\TypeComparator;

use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;
use Rector\NodeTypeResolver\PHPStan\TypeHasher;
use Rector\Reflection\ReflectionResolver;
use Rector\StaticTypeMapper\StaticTypeMapper;
use Rector\StaticTypeMapper\ValueObject\Type\AliasedObjectType;
use Rector\TypeDeclaration\TypeNormalizer;
final class TypeComparator
{
    /**
     * @readonly
     * @var \Rector\NodeTypeResolver\PHPStan\TypeHasher
     */
    private $typeHasher;
    /**
     * @readonly
     * @var \Rector\TypeDeclaration\TypeNormalizer
     */
    private $typeNormalizer;
    /**
     * @readonly
     * @var \Rector\StaticTypeMapper\StaticTypeMapper
     */
    private $staticTypeMapper;
    /**
     * @readonly
     * @var \Rector\NodeTypeResolver\TypeComparator\ArrayTypeComparator
     */
    private $arrayTypeComparator;
    /**
     * @readonly
     * @var \Rector\NodeTypeResolver\TypeComparator\ScalarTypeComparator
     */
    private $scalarTypeComparator;
    /**
     * @readonly
     * @var \Rector\Reflection\ReflectionResolver
     */
    private $reflectionResolver;
    public function __construct(TypeHasher $typeHasher, TypeNormalizer $typeNormalizer, StaticTypeMapper $staticTypeMapper, \Rector\NodeTypeResolver\TypeComparator\ArrayTypeComparator $arrayTypeComparator, \Rector\NodeTypeResolver\TypeComparator\ScalarTypeComparator $scalarTypeComparator, ReflectionResolver $reflectionResolver)
    {
        $this->typeHasher = $typeHasher;
        $this->typeNormalizer = $typeNormalizer;
        $this->staticTypeMapper = $staticTypeMapper;
        $this->arrayTypeComparator = $arrayTypeComparator;
        $this->scalarTypeComparator = $scalarTypeComparator;
        $this->reflectionResolver = $reflectionResolver;
    }
    public function areTypesEqual(Type $firstType, Type $secondType) : bool
    {
        $firstTypeHash = $this->typeHasher->createTypeHash($firstType);
        $secondTypeHash = $this->typeHasher->createTypeHash($secondType);
        if ($firstTypeHash === $secondTypeHash) {
            return \true;
        }
        if ($this->scalarTypeComparator->areEqualScalar($firstType, $secondType)) {
            return \true;
        }
        // aliases and types
        if ($this->areAliasedObjectMatchingFqnObject($firstType, $secondType)) {
            return \true;
        }
        $firstType = $this->typeNormalizer->normalizeArrayOfUnionToUnionArray($firstType);
        $secondType = $this->typeNormalizer->normalizeArrayOfUnionToUnionArray($secondType);
        if ($this->typeHasher->areTypesEqual($firstType, $secondType)) {
            return \true;
        }
        // is template of
        return $this->areArrayTypeWithSingleObjectChildToParent($firstType, $secondType);
    }
    public function arePhpParserAndPhpStanPhpDocTypesEqual(Node $phpParserNode, TypeNode $phpStanDocTypeNode, Node $node) : bool
    {
        $phpParserNodeType = $this->staticTypeMapper->mapPhpParserNodePHPStanType($phpParserNode);
        $phpStanDocType = $this->staticTypeMapper->mapPHPStanPhpDocTypeNodeToPHPStanType($phpStanDocTypeNode, $node);
        if (!$this->areTypesEqual($phpParserNodeType, $phpStanDocType) && $this->isSubtype($phpStanDocType, $phpParserNodeType)) {
            return \false;
        }
        // normalize bool union types
        $phpParserNodeType = $this->normalizeConstantBooleanType($phpParserNodeType);
        $phpStanDocType = $this->normalizeConstantBooleanType($phpStanDocType);
        // is scalar replace by another - remove it?
        $areDifferentScalarTypes = $this->scalarTypeComparator->areDifferentScalarTypes($phpParserNodeType, $phpStanDocType);
        if (!$areDifferentScalarTypes && !$this->areTypesEqual($phpParserNodeType, $phpStanDocType)) {
            return \false;
        }
        if ($this->areTypesSameWithLiteralTypeInPhpDoc($areDifferentScalarTypes, $phpStanDocType, $phpParserNodeType)) {
            return \false;
        }
        return $this->isThisTypeInFinalClass($phpStanDocType, $phpParserNodeType, $phpParserNode);
    }
    public function isSubtype(Type $checkedType, Type $mainType) : bool
    {
        if ($mainType instanceof MixedType) {
            return \false;
        }
        if (!$mainType instanceof ArrayType) {
            return $mainType->isSuperTypeOf($checkedType)->yes();
        }
        if (!$checkedType instanceof ArrayType) {
            return $mainType->isSuperTypeOf($checkedType)->yes();
        }
        return $this->arrayTypeComparator->isSubtype($checkedType, $mainType);
    }
    private function areAliasedObjectMatchingFqnObject(Type $firstType, Type $secondType) : bool
    {
        if ($firstType instanceof AliasedObjectType && $secondType instanceof ObjectType) {
            return $firstType->getFullyQualifiedName() === $secondType->getClassName();
        }
        if (!$firstType instanceof ObjectType) {
            return \false;
        }
        if (!$secondType instanceof AliasedObjectType) {
            return \false;
        }
        return $secondType->getFullyQualifiedName() === $firstType->getClassName();
    }
    /**
     * E.g. class A extends B, class B → A[] is subtype of B[] → keep A[]
     */
    private function areArrayTypeWithSingleObjectChildToParent(Type $firstType, Type $secondType) : bool
    {
        if (!$firstType instanceof ArrayType) {
            return \false;
        }
        if (!$secondType instanceof ArrayType) {
            return \false;
        }
        $firstArrayItemType = $firstType->getItemType();
        $secondArrayItemType = $secondType->getItemType();
        return $this->isMutualObjectSubtypes($firstArrayItemType, $secondArrayItemType);
    }
    private function isMutualObjectSubtypes(Type $firstArrayItemType, Type $secondArrayItemType) : bool
    {
        if (!$firstArrayItemType instanceof ObjectType) {
            return \false;
        }
        if (!$secondArrayItemType instanceof ObjectType) {
            return \false;
        }
        if ($firstArrayItemType->isSuperTypeOf($secondArrayItemType)->yes()) {
            return \true;
        }
        return $secondArrayItemType->isSuperTypeOf($firstArrayItemType)->yes();
    }
    private function normalizeConstantBooleanType(Type $type) : Type
    {
        return TypeTraverser::map($type, static function (Type $type, callable $callable) : Type {
            if ($type instanceof ConstantBooleanType) {
                return new BooleanType();
            }
            return $callable($type);
        });
    }
    private function areTypesSameWithLiteralTypeInPhpDoc(bool $areDifferentScalarTypes, Type $phpStanDocType, Type $phpParserNodeType) : bool
    {
        return $areDifferentScalarTypes && $phpStanDocType instanceof ConstantScalarType && $phpParserNodeType->isSuperTypeOf($phpStanDocType)->yes();
    }
    private function isThisTypeInFinalClass(Type $phpStanDocType, Type $phpParserNodeType, Node $node) : bool
    {
        /**
         * Special case for $this/(self|static) compare
         *
         * $this refers to the exact object identity, not just the same type. Therefore, it's valid and should not be removed
         * @see https://wiki.php.net/rfc/this_return_type for more context
         */
        if ($phpStanDocType instanceof ThisType && $phpParserNodeType instanceof StaticType) {
            return \false;
        }
        $isStaticReturnDocTypeWithThisType = $phpStanDocType instanceof StaticType && $phpParserNodeType instanceof ThisType;
        if (!$isStaticReturnDocTypeWithThisType) {
            return \true;
        }
        $classReflection = $this->reflectionResolver->resolveClassReflection($node);
        if (!$classReflection instanceof ClassReflection || !$classReflection->isClass()) {
            return \false;
        }
        return $classReflection->isFinalByKeyword();
    }
}
