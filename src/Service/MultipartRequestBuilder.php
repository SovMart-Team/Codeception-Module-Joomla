<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Dto\RequestData;
use JoomlaCodeception\Dto\UploadFile;
use JoomlaCodeception\Exception\UnsupportedEnvironmentException;

final readonly class MultipartRequestBuilder
{
    /** @return array<string, mixed> */
    public function files(RequestData $request, FixtureProvider $provider): array
    {
        $files = [];

        foreach ($request->uploadFiles() as $upload) {
            if (!$upload instanceof UploadFile) {
                throw new \RuntimeException('Multipart uploads must contain only UploadFile values.');
            }

            if ($upload->error === UPLOAD_ERR_NO_FILE || $upload->source === null) {
                continue;
            }

            if ($upload->error !== UPLOAD_ERR_OK) {
                throw new UnsupportedEnvironmentException(sprintf(
                    'Upload error %d cannot be reproduced reliably through PhpBrowser.',
                    $upload->error,
                ));
            }

            $path = $provider->fixturePath($upload->source);
            $leaf = [
                'name'     => $upload->uploadName ?? basename($path),
                'type'     => $upload->clientMime ?? '',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($path),
            ];
            $this->assignNested($files, $upload->inputName, $leaf);
        }

        return $files;
    }

    /**
     * PHP applies MAX_FILE_SIZE only when the field precedes the related file part.
     * PhpBrowser's Guzzle connector always emits files first, so this bounded raw
     * encoder is used only by that functional scenario.
     *
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $files
     * @return array{contentType: string, body: string}
     */
    public function fieldsBeforeFilesBody(array $fields, array $files): array
    {
        $boundary = '----JoomlaCodeception' . bin2hex(random_bytes(12));
        $parts    = [];
        $this->appendFields($parts, $fields);
        $this->appendFiles($parts, $files);
        $body = '';

        foreach ($parts as $part) {
            $body .= '--' . $boundary . "\r\n" . $part . "\r\n";
        }

        $body .= '--' . $boundary . "--\r\n";

        return [
            'contentType' => 'multipart/form-data; boundary=' . $boundary,
            'body'        => $body,
        ];
    }

    /** @param list<string> $parts @param array<string, mixed> $fields */
    private function appendFields(array &$parts, array $fields, string $prefix = ''): void
    {
        foreach ($fields as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

            if (is_array($value)) {
                $this->appendFields($parts, $value, $name);
                continue;
            }

            $parts[] = 'Content-Disposition: form-data; name="' . $this->quote($name) . "\"\r\n\r\n" . (string) $value;
        }
    }

    /** @param list<string> $parts @param array<string, mixed> $files */
    private function appendFiles(array &$parts, array $files, string $prefix = ''): void
    {
        foreach ($files as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

            if (is_array($value) && !array_key_exists('tmp_name', $value)) {
                $this->appendFiles($parts, $value, $name);
                continue;
            }

            if (!is_array($value) || empty($value['tmp_name'])) {
                continue;
            }

            $contents = file_get_contents((string) $value['tmp_name']);

            if (!is_string($contents)) {
                throw new \RuntimeException('Unable to read multipart upload fixture.');
            }

            $parts[] = 'Content-Disposition: form-data; name="' . $this->quote($name)
                . '"; filename="' . $this->quote((string) ($value['name'] ?? 'upload.bin')) . "\"\r\n"
                . 'Content-Type: ' . (string) ($value['type'] ?? 'application/octet-stream') . "\r\n\r\n"
                . $contents;
        }
    }

    private function quote(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    /** @param array<string, mixed> $target @param array<string, mixed> $leaf */
    private function assignNested(array &$target, string $inputName, array $leaf): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]*(?:\[[A-Za-z0-9_.-]*\])*$/', $inputName)) {
            throw new \RuntimeException(sprintf('Invalid multipart input name: %s', $inputName));
        }

        preg_match('/^([A-Za-z][A-Za-z0-9_.-]*)(.*)$/', $inputName, $rootMatch);
        $segments = [$rootMatch[1]];
        preg_match_all('/\[([A-Za-z0-9_.-]*)\]/', $rootMatch[2], $nestedMatches);

        foreach ($nestedMatches[1] as $segment) {
            $segments[] = $segment === '' ? null : $segment;
        }

        $cursor = &$target;

        foreach ($segments as $index => $segment) {
            if ($index === array_key_last($segments)) {
                if ($segment === null) {
                    $cursor[] = $leaf;

                    return;
                }

                if (array_key_exists($segment, $cursor)) {
                    throw new \RuntimeException(sprintf('Duplicate multipart input path: %s', $inputName));
                }

                $cursor[$segment] = $leaf;

                return;
            }

            if ($segment === null) {
                $cursor[] = [];
                $segment  = array_key_last($cursor);
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }
    }
}
