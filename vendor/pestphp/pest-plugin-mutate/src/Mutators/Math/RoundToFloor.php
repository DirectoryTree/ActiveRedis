<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Math;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionReplaceMutator;

class RoundToFloor extends AbstractFunctionReplaceMutator
{
    public const SET = 'Math';

    public const DESCRIPTION = 'Replaces `round` function with `floor` function.';

    public const DIFF = <<<'DIFF'
        $a = round(1.2);  // [tl! remove]
        $a = floor(1.2);  // [tl! add]
        DIFF;

    public static function from(): string
    {
        return 'round';
    }

    public static function to(): string
    {
        return 'floor';
    }
}
