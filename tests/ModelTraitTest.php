<?php

namespace DirectoryTree\ActiveRedis\Tests;

use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithTraitInitializer;

it('initializes the model trait', function () {
    ModelStubWithTraitInitializer::$traitInitialized = false;

    new ModelStubWithTraitInitializer;

    expect(ModelStubWithTraitInitializer::$traitInitialized)->toBeTrue();
});
