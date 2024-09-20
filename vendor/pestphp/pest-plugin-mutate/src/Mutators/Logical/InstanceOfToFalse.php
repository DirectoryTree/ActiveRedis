<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Logical;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Name;

class InstanceOfToFalse extends AbstractMutator
{
    public const SET = 'Logical';

    public const DESCRIPTION = 'Converts `instanceof` to `false`.';

    public const DIFF = <<<'DIFF'
        if ($a instanceof $b) {  // [tl! remove]
        if (false) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Instanceof_::class];
    }

    public static function mutate(Node $node): Node
    {
        return new ConstFetch(new Name('false'));
    }
}
