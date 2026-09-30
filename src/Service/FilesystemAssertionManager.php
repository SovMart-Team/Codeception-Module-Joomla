<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Contract\CountFilesInterface;
use JoomlaCodeception\Contract\DontSeeFileInterface;
use JoomlaCodeception\Contract\SeeFileInterface;
use JoomlaCodeception\Dto\FileExpectation;
use PHPUnit\Framework\Assert;

final readonly class FilesystemAssertionManager
{
    public function __construct(private FileFixtureManager $files)
    {
    }

    /** @param list<object> $capabilities */
    public function assert(array $capabilities, FixtureProvider $provider): void
    {
        foreach ($capabilities as $capability) {
            if ($capability instanceof SeeFileInterface) {
                foreach ($capability->seeFiles($provider) as $expectation) {
                    $this->assertFile($expectation, true);
                }
            }

            if ($capability instanceof DontSeeFileInterface) {
                foreach ($capability->dontSeeFiles($provider) as $expectation) {
                    $this->assertFile($expectation, false);
                }
            }

            if ($capability instanceof CountFilesInterface) {
                foreach ($capability->countFiles($provider) as $expectation) {
                    $paths = glob($this->files->path($expectation->root, $expectation->relativePath)) ?: [];
                    Assert::assertCount($expectation->count, $paths);
                }
            }
        }
    }

    private function assertFile(FileExpectation $expectation, bool $present): void
    {
        $path = $this->files->path($expectation->root, $expectation->relativePath);

        if (!$present) {
            Assert::assertFileDoesNotExist($path);

            return;
        }

        Assert::assertFileExists($path);

        if ($expectation->size !== null) {
            Assert::assertSame($expectation->size, filesize($path));
        }

        if ($expectation->sha256 !== null) {
            Assert::assertSame($expectation->sha256, hash_file('sha256', $path));
        }

        if ($expectation->exactContent !== null) {
            Assert::assertSame($expectation->exactContent, file_get_contents($path));
        }

        if ($expectation->mime !== null) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            Assert::assertSame($expectation->mime, $mime);
        }

        if ($expectation->imageWidth !== null || $expectation->imageHeight !== null) {
            $image = getimagesize($path);
            Assert::assertIsArray($image, sprintf('File is not a readable image: %s', $path));

            if ($expectation->imageWidth !== null) {
                Assert::assertSame($expectation->imageWidth, $image[0]);
            }

            if ($expectation->imageHeight !== null) {
                Assert::assertSame($expectation->imageHeight, $image[1]);
            }
        }

        foreach ($expectation->contentContains as $fragment) {
            Assert::assertStringContainsString($fragment, (string) file_get_contents($path));
        }

        foreach ($expectation->contentNotContains as $fragment) {
            Assert::assertStringNotContainsString($fragment, (string) file_get_contents($path));
        }
    }
}
