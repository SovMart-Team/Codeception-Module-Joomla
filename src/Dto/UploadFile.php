<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class UploadFile
{
    public function __construct(
        public ?string $source,
        public string $inputName = 'file',
        public ?string $uploadName = null,
        public ?string $clientMime = null,
        public int $error = UPLOAD_ERR_OK,
        public ?string $sourceMime = null,
        public ?int $sourceMinBytes = null,
        public ?int $sourceMaxBytes = null,
    ) {
    }
}
