<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Array;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapArrayUnique extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'Array';

    public const DESCRIPTION = 'Unwraps `array_unique` calls.';

    public const DIFF = <<<'DIFF'
        $a = array_unique([1, 2, 3]);  // [tl! remove]
        $a = [1, 2, 3];  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'array_unique';
    }
}
