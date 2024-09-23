<?php

use DirectoryTree\ActiveRedis\Exceptions\AttributeNotSearchableException;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithCustomKey;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithSearchable;

it('generates query using where clauses', function () {
    $query = query(new ModelStubWithSearchable);

    expect($query->getQuery())->toBe('model_stub_with_searchables:id:*:company_id:*:user_id:*');

    $query->where('user_id', '1');

    expect($query->getQuery())->toBe('model_stub_with_searchables:id:*:company_id:*:user_id:1');

    $query->where('company_id', '2');

    expect($query->getQuery())->toBe('model_stub_with_searchables:id:*:company_id:2:user_id:1');
});

it('throws exception with where clause for attribute that is not searchable', function () {
    $query = query(new ModelStub);

    $query->where('foo', '1');
})->throws(AttributeNotSearchableException::class, 'The attribute [foo] is not searchable on the model [DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub].');

it('generates query using model prefix', function () {
    expect(query(new ModelStub)->getQuery())->toBe('model_stubs:id:*');
});

it('generates query using custom key', function () {
    expect(query(new ModelStubWithCustomKey)->getQuery())->toBe('model_stub_with_custom_keys:custom:*');
});

it('generates query using searchable attributes', function () {
    expect(query(new ModelStubWithSearchable)->getQuery())->toBe('model_stub_with_searchables:id:*:company_id:*:user_id:*');
});
