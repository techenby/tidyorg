<?php

use App\Modules\StoredFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;

test('there is no link without a file', function () {
    expect(StoredFile::url(null))->toBeNull()
        ->and(StoredFile::url(''))->toBeNull();
});

test('a file on the private local disk gets a signed link that serves it', function () {
    Storage::put('teams/1/photo.txt', 'contents');

    $url = StoredFile::url('teams/1/photo.txt');

    expect($url)->toContain('signature=');

    $this->get($url)->assertOk();
    $this->get(strtok($url, '?'))->assertForbidden();

    Storage::delete('teams/1/photo.txt');
});

test('a file on a public disk gets its public link', function () {
    expect(StoredFile::url('teams/1/photo.txt', 'public'))->toEndWith('/storage/teams/1/photo.txt');
});

test('a file on object storage gets a temporary link', function () {
    config(['filesystems.disks.s3' => [
        'driver' => 's3',
        'key' => 'key',
        'secret' => 'secret',
        'region' => 'us-east-1',
        'bucket' => 'tidyorg',
    ]]);

    expect(StoredFile::url('teams/1/photo.txt', 's3'))
        ->toContain('tidyorg')
        ->toContain('X-Amz-Signature=');
})->skip(! class_exists(AwsS3V3Adapter::class), 'The S3 driver is not installed.');
