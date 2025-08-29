<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithCustomRepository;

it('can use custom null repository', function () {
    expect(ModelStubWithCustomRepository::create())->toBeinstanceOf(ModelStubWithCustomRepository::class);
    expect(ModelStubWithCustomRepository::all())->toBeEmpty();
});
