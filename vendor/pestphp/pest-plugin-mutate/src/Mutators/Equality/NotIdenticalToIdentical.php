<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Equality;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;

class NotIdenticalToIdentical extends AbstractMutator
{
    public const SET = 'Equality';

    public const DESCRIPTION = 'Converts the not identical operator to the identical operator.';

    public const DIFF = <<<'DIFF'
        if ($a !== $b) {  // [tl! remove]
        if ($a === $b) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [NotIdentical::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var NotIdentical $node */
        return new Identical($node->left, $node->right, $node->getAttributes());
    }
}
