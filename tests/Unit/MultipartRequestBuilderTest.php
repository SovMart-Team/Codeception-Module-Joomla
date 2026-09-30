<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use Codeception\Lib\Connector\Guzzle;
use JoomlaCodeception\Dto\RequestData;
use JoomlaCodeception\Dto\UploadFile;
use JoomlaCodeception\Service\FixtureProvider;
use JoomlaCodeception\Service\MultipartRequestBuilder;
use PHPUnit\Framework\TestCase;

final class MultipartRequestBuilderTest extends TestCase
{
    public function testPreservesRepeatedNestedUploadsAndTransportMetadata(): void
    {
        $request = new RequestData(uploads: [
            new UploadFile('SupportAutoload.php', 'files[]', 'first.txt', 'text/first'),
            new UploadFile('SupportAutoload.php', 'files[]', 'second.txt', 'text/second'),
            new UploadFile('SupportAutoload.php', 'jform[documents][][file]', 'nested.txt', 'text/nested'),
        ]);
        $files = (new MultipartRequestBuilder())->files($request, new FixtureProvider(__DIR__));

        self::assertSame('first.txt', $files['files'][0]['name']);
        self::assertSame('second.txt', $files['files'][1]['name']);
        self::assertSame('nested.txt', $files['jform']['documents'][0]['file']['name']);

        $parts = (new InspectableGuzzleConnector())->multipartParts($files);

        try {
            self::assertSame(['files[0]', 'files[1]', 'jform[documents][0][file]'], array_column($parts, 'name'));
            self::assertSame(['first.txt', 'second.txt', 'nested.txt'], array_column($parts, 'filename'));
            self::assertSame('text/first', $parts[0]['headers']['content-type']);
            self::assertSame('text/second', $parts[1]['headers']['content-type']);
            self::assertSame('text/nested', $parts[2]['headers']['content-type']);
        } finally {
            foreach ($parts as $part) {
                if (is_resource($part['contents'] ?? null)) {
                    fclose($part['contents']);
                }
            }
        }
    }

    public function testBuildsUploadFromNamedAssetRoot(): void
    {
        $request = new RequestData(upload: new UploadFile(
            '@security/SupportAutoload.php',
            'jform[file]',
            'payload.php.jpg',
            'image/jpeg'
        ));
        $provider = new FixtureProvider(__DIR__, ['security' => __DIR__]);
        $files    = (new MultipartRequestBuilder())->files($request, $provider);

        self::assertSame('payload.php.jpg', $files['jform']['file']['name']);
        self::assertSame('image/jpeg', $files['jform']['file']['type']);
        self::assertSame(realpath(__DIR__ . '/SupportAutoload.php'), $files['jform']['file']['tmp_name']);
    }

    public function testCanEncodeMaxFileSizeBeforeUploadPart(): void
    {
        $builder = new MultipartRequestBuilder();
        $request = new RequestData(upload: new UploadFile(
            'SupportAutoload.php',
            'jform[icon]',
            'valid.jpg',
            'image/jpeg'
        ));
        $files     = $builder->files($request, new FixtureProvider(__DIR__));
        $multipart = $builder->fieldsBeforeFilesBody(
            ['MAX_FILE_SIZE' => 1, 'jform' => ['title' => 'Upload error']],
            $files
        );

        self::assertStringStartsWith('multipart/form-data; boundary=', $multipart['contentType']);
        self::assertLessThan(
            strpos($multipart['body'], 'name="jform[icon]"'),
            strpos($multipart['body'], 'name="MAX_FILE_SIZE"')
        );
        self::assertStringContainsString('filename="valid.jpg"', $multipart['body']);
    }
}

final class InspectableGuzzleConnector extends Guzzle
{
    /** @param array<string, mixed> $files @return array<int, mixed> */
    public function multipartParts(array $files): array
    {
        return $this->mapFiles($files);
    }
}
