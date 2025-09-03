<?php

use DirectoryTree\ActiveRedis\Repositories\ArrayRepository;
use DirectoryTree\ActiveRedis\Repositories\RedisRepository;
use DirectoryTree\ActiveRedis\Repositories\RepositoryFactory;
use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStub;
use DirectoryTree\ActiveRedis\Tests\Stubs\NullRepository;

it('can resolve redis repository', function () {
    $repository = (new RepositoryFactory)->make(new ModelStub);

    expect($repository)->toBeInstanceOf(RedisRepository::class);
});

it('can resolve array repository', function () {
    ModelStub::setRepository('array');

    $repository = (new RepositoryFactory)->make(new ModelStub);

    expect($repository)->toBeInstanceOf(ArrayRepository::class);
});

it('can resolve custom repository', function () {
    ModelStub::setRepository(NullRepository::class);

    $repository = (new RepositoryFactory)->make(new ModelStub);

    expect($repository)->toBeInstanceOf(NullRepository::class);
});
