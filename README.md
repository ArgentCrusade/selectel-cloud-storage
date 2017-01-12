# argentcrusade/selectel-cloud-storage

[![Build Status][ico-travis]][link-travis]
[![StyleCI][ico-styleci]][link-styleci]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)

Unofficial PHP SDK for [Selectel Cloud Storage](https://selectel.com/services/cloud-storage/) API.

## Requirements
This package requires PHP 5.6 or higher.

## Installation

You can install the package via composer:

``` bash
$ composer require argentcrusade/selectel-cloud-storage
```

## Usage

### Initialize Storage
``` php
use ArgentCrusade\Selectel\CloudStorage\Api\ApiClient;
use ArgentCrusade\Selectel\CloudStorage\CloudStorage;

$apiClient = new ApiClient('username', 'password');
$storage = new CloudStorage($apiClient);
```

### CloudStorage
`ArgentCrusade\Selectel\CloudStorage\CloudStorage` class allows you to access and create containers.

```php
// Retrieve single container.
$container = $storage->getContainer('my-container');

// Create new container.
$type = 'public'; // Can be 'public', 'private' or 'gallery'.
$newContainer = $storage->createContainer('new-container-name', $type);

// Retrieve containers list.
$containers = $storage->containers();
```

### Containers Collection
`CloudStorage::containers` method returns instance of `ArgentCrusade\Selectel\CloudStorage\Collections\Collection` class with  retrieved containers objects. This collection object implements `ArrayAccess`, `Countable`, `Iterator` and `JsonSerializable` interfaces, what makes you able to do these things:


```php
$containers = $storage->containers();

// Check if container exists.
if ($containers->has('my-container')) {
	// Container exists.
}

// Get containers count.
$containersCount = count($containers); // Or $containers->count();

// Access specific container.
$container = $containers['my-container']; // Or $containers->get('my-container');

// Iterate through containers.
foreach ($containers as $container) {
	echo 'Container "'.$container->name().'" has size of '.$container->size().' bytes';
}

// Send JSON representation of collection.
header('Content-Type: application/json;charset=utf-8');
echo json_encode($containers);
```
### Container Instance
Container that you've retrieved from Containers Collection is an `ArgentCrusade\Selectel\CloudStorage\Container` instance object which implements `Countable` and `JsonSerializable` interfaces.

```php
$container = $containers->get('my-container');

// Get container attributes.
$name = $container->name(); // 'container-name'.
$type = $container->type(); // 'private' or 'public'.
$filesCount = $container->filesCount(); // or count($container); // or $container->count();
$sizeInBytes = $container->size();
$uploadedBytes = $container->uploadedBytes(); // Total number of bytes uploaded to container (rx_bytes).
$downloadedBytes = $container->downloadedBytes(); // Total number of bytes downloaded from container (tx_bytes).
$json = json_encode($container); // JSON representation of container.

// Helpers.
$isPublicContainer = $container->isPublic(); // true or false.
$isPrivateContainer = $container->isPrivate(); // true or false.

// Files.
// All files (first 10000 by default).
$files = $container->files();

// Files from specific directory.
$filesFromDirectory = $container->files('/directory/path');

// Files that satisfies given prefix.
// Useful for retrieving files with same name pattern:
// image-001.jpg, image-002.jpg, image-003.jpg, etc.
$filesWithPrefix = $container->files(null, '/directory/image-');

// Exact file.
$exactFile = $container->files(null, '/path/to/file.txt'); // Collection of 1 file.

// Get single file instance.
$file = $container->getFile('/path/to/file.txt');

// Delete container.
// Note: container must be empty!
$container->delete();
```

#### File Uploads
`Container` class provides `uploadFromString` method to upload file contents and `uploadFromStream` method to upload file from stream.

```php
// Upload file from string contents.
$contents = file_get_contents('test.txt');
$etag = $container->uploadFromString('/path/to/file.txt', $contents);

// Upload file from stream.
$stream = fopen('test.txt', 'r');
$etag = $container->uploadFromStream('/path/to/file.txt', $stream);
```
Both methods accepts `array $params` as third optional argument.

```php
$params = [
	'contentType' => 'application/json',
    'contentDisposition' => 'attachment; filename="filename.json"',
    'deleteAfter' => $seconds, // File will be deleted in X seconds after upload.
    'deleteAt' => strtotime('next monday'), // File will be deleted at given UNIX timestamp.
];
```
Also, `uploadFromString` method accepts 4th argument `bool $verifyChecksum`. If true, Selectel will perform MD5 checksum comparison and if something went wrong during upload process, it won't accept file and exception will be thrown. This option is enabled by default for `uploadFromString` method.

#### File Instance
When you retrieve collection of files via `Contrainer::files` method you get array of arrays:

```php
$files = $container->files();
$firstFile = $files->get(0);
/*
$firstFile will be something like this:

[
	'bytes' => 31,
    'content_type' => 'text/html',
    'hash' => 'b302ffc3b75770453e96c1348e30eb93',
    'last_modified': "2013-05-27T14:42:04.669760",
    'name': "my_index.html",
]
*/
```

But when you're using `Container::getFile` method, you receive instance of `ArgentCrusade\Selectel\CloudStorage\File` class that implements `JsonSerializable` interface. With this object you can perform operations such as renaming, copying and deleting file.

```php
$file = $container->getFile('/path/to/file.txt');

// Get file attributes.
$containerName = $file->container(); // 'my-container'
$path = $file->path(); // Full path to file (from container root): '/path/to/file.txt'
$directory = $file->directory(); // Full path to directory (from container root) without filename: '/path/to'
$name = $file->name(); // Filename 'file.txt'
$sizeInBytes = $file->size(); // File size in bytes.
$contentType = $file->contentType(); // 'text/plain'
$lastModifiedAt = $file->lastModifiedAt(); // '2013-05-27T14:42:04.669760'
$etag = $file->etag(); // md5 hash of file contents.
$isDeleted = $file->isDeleted(); // Becomes true only after deletion operation.
$json = json_encode($file); // JSON representation of file.

// Read file.
$contents = $file->read(); // Read file and return string.
$resource = $file->readStream(); // Read file and return stream resource.

// If you need PSR-7's StreamInterface instead of resource, provide $psr7Stream = true argument to File::readStream method:
$psr7Stream = $file->readStream(true); // Instance of \Psr\Http\Message\StreamInterface

// Rename file.
$file->rename('new-name.txt'); // File will be placed in the same directory.

// Copy file.
$file->copy('/path/to/new/file.txt'); // Provide full path to destination file (from container root).

// Copy file to another container.
$file->copy('/path/to/file.txt', 'my-second-container');

// Delete file.
// Note: after file deletion you won't be able to perform
// any of operations listed above (except $file->isDeleted() one).
$file->delete();
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email zurbaev@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://poser.pugx.org/argentcrusade/selectel-cloud-storage/version?format=flat
[ico-license]: https://poser.pugx.org/argentcrusade/selectel-cloud-storage/license?format=flat
[ico-travis]: https://api.travis-ci.org/ArgentCrusade/selectel-cloud-storage.svg?branch=master
[ico-styleci]: https://styleci.io/repos/78674486/shield?branch=master&style=flat

[link-packagist]: https://packagist.org/packages/argentcrusade/selectel-cloud-storage
[link-travis]: https://travis-ci.org/ArgentCrusade/selectel-cloud-storage
[link-styleci]: https://styleci.io/repos/78674486
[link-author]: https://github.com/tzurbaev
