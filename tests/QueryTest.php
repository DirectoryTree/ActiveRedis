<?php

use DirectoryTree\ActiveRedis\Exceptions\AttributeNotQueryableException;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithCustomKey;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithQueryable;

it('generates query using where clauses', function () {
    $query = query(new ModelStubWithQueryable);

    expect($query->getQuery())->toBe('model_stub_with_queryables:id:*:company_id:*:user_id:*');

    $query->where('user_id', '1');

    expect($query->getQuery())->toBe('model_stub_with_queryables:id:*:company_id:*:user_id:1');

    $query->where('company_id', '2');

    expect($query->getQuery())->toBe('model_stub_with_queryables:id:*:company_id:2:user_id:1');
});

it('throws exception with where clause for attribute that is not queryable', function () {
    $query = query(new ModelStub);

    $query->where('foo', '1');
})->throws(AttributeNotQueryableException::class, 'The attribute [foo] is not queryable on the model [DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub].');

it('generates query using model prefix', function () {
    expect(query(new ModelStub)->getQuery())->toBe('model_stubs:id:*');
});

it('generates query using custom key', function () {
    expect(query(new ModelStubWithCustomKey)->getQuery())->toBe('model_stub_with_custom_keys:custom:*');
});

it('generates query using queryable', function () {
    expect(query(new ModelStubWithQueryable)->getQuery())->toBe('model_stub_with_queryables:id:*:company_id:*:user_id:*');
});
