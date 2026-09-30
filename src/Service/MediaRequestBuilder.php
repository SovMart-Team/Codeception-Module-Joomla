<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Dto\RequestData;

final readonly class MediaRequestBuilder
{
    /** @param array<string, mixed> $fields @return array<string, mixed> */
    public function body(RequestData $request, FixtureProvider $provider, array $fields): array
    {
        if ($request->adapterPath !== null) {
            $fields['path'] = $request->adapterPath;
        }

        if ($request->content !== null) {
            $content           = file_get_contents($provider->fixturePath($request->content->source));
            $fields['content'] = base64_encode(is_string($content) ? $content : '');
        }

        return $fields;
    }
}
