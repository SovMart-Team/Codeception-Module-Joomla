<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class RequestData
{
    /**
     * @param array<string, mixed> $fields
     * @param list<FixtureFile> $preparedFiles
     * @param list<UploadFile> $uploads
     */
    public function __construct(
        public array $fields = [],
        public CsrfMode $csrfMode = CsrfMode::Auto,
        public RedirectPolicy $redirectPolicy = RedirectPolicy::Stop,
        public ?string $mockCase = null,
        public array $preparedFiles = [],
        public ?UploadFile $upload = null,
        public array $uploads = [],
        public ?string $task = null,
        public ControlFieldPolicy $controlFieldPolicy = ControlFieldPolicy::Merge,
        public ?string $adapterPath = null,
        public ?FixtureFile $content = null,
        public ?ListSelection $selection = null,
    ) {
    }

    /** @return list<UploadFile> */
    public function uploadFiles(): array
    {
        return $this->upload === null ? $this->uploads : [$this->upload, ...$this->uploads];
    }

    public function withUpload(UploadFile $upload): self
    {
        return new self(
            fields: $this->fields,
            csrfMode: $this->csrfMode,
            redirectPolicy: $this->redirectPolicy,
            mockCase: $this->mockCase,
            preparedFiles: $this->preparedFiles,
            upload: $upload,
            uploads: $this->uploads,
            task: $this->task,
            controlFieldPolicy: $this->controlFieldPolicy,
            adapterPath: $this->adapterPath,
            content: $this->content,
            selection: $this->selection,
        );
    }
}
