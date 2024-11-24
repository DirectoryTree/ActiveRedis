<?php

use DirectoryTree\ActiveRedis\Repositories\ArrayRepository;
use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStub;

beforeAll(fn () => ModelStub::setRepository('array'));
afterAll(fn () => ModelStub::setRepository('redis'));

it('can use array repository', function () {
    ModelStub::create();
    ModelStub::create();

    $repository = ModelStub::query()->getRepository();

    expect($repository)->toBeInstanceOf(ArrayRepository::class);

    $results = iterator_to_array(
        $repository->chunk('*', 100)
    );

    expect($results[0])->toHaveCount(2);
});
