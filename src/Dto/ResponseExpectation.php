<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class ResponseExpectation
{
    /**
     * @param list<string> $contains
     * @param list<string> $notContains
     * @param list<string> $uploadedImageUrlNotContains
     * @param list<string> $watchedTables
     */
    public function __construct(
        public ResponseType $type = ResponseType::Any,
        public ?string $contentTypeContains = null,
        public ?string $redirectContains = null,
        public bool $loginForm = false,
        public array $contains = [],
        public array $notContains = [],
        public bool $noSideEffects = false,
        public ?int $uploadedImageSuccess = null,
        public ?string $uploadedImageUrlPrefix = null,
        public ?string $uploadedImageUrlSuffix = null,
        public array $uploadedImageUrlNotContains = [],
        public array $watchedTables = [],
    ) {
    }
}
