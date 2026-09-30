<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use JoomlaCodeception\Dto\RequestData;
use JoomlaCodeception\Dto\UploadFile;
use PHPUnit\Framework\TestCase;

final class RequestDataTest extends TestCase
{
    public function testWithUploadReturnsImmutableOverlay(): void
    {
        $existing = new UploadFile('existing.txt', 'files[]');
        $overlay  = new UploadFile('@security/payload.txt', 'jform[file]', 'unsafe.txt');
        $request  = new RequestData(fields: ['id' => 7], uploads: [$existing], task: 'item.save');
        $result   = $request->withUpload($overlay);

        self::assertNull($request->upload);
        self::assertSame([$existing], $request->uploadFiles());
        self::assertSame([$overlay, $existing], $result->uploadFiles());
        self::assertSame(['id' => 7], $result->fields);
        self::assertSame('item.save', $result->task);
    }
}
