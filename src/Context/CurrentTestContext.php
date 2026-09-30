<?php

declare(strict_types=1);

namespace JoomlaCodeception\Context;

use JoomlaCodeception\Contract\RequestFixtureInterface;
use JoomlaCodeception\Contract\ResponseResultInterface;
use JoomlaCodeception\Dto\RequestData;
use JoomlaCodeception\Dto\ResponseExpectation;
use JoomlaCodeception\Dto\SideEffectSnapshot;
use JoomlaCodeception\Service\FixtureProvider;

final class CurrentTestContext
{
    /** @var array<string, mixed> */
    public array $identity = [];

    public bool $identityResolved = false;

    public bool $configurationApplied = false;

    public bool $databaseLoaded = false;

    public bool $filesLoaded = false;

    public bool $authenticated = false;

    public ?SideEffectSnapshot $sideEffectSnapshot = null;

    private ?RequestData $requestData = null;

    private ?ResponseExpectation $responseExpectation = null;

    /** @param list<object> $capabilities */
    public function __construct(
        public readonly string $cest,
        public readonly string $controller,
        public readonly string $method,
        public readonly string $directory,
        public readonly array $capabilities,
        public readonly FixtureProvider $provider,
    ) {
    }

    public function requestData(): RequestData
    {
        if ($this->requestData === null) {
            $fixture           = $this->one(RequestFixtureInterface::class);
            $this->requestData = $fixture->getRequest($this->provider);
        }

        return $this->requestData;
    }

    public function responseExpectation(): ResponseExpectation
    {
        if ($this->responseExpectation === null) {
            $result                    = $this->one(ResponseResultInterface::class);
            $this->responseExpectation = $result->response($this->provider);
        }

        return $this->responseExpectation;
    }

    /** @template T of object @param class-string<T> $interface @return T */
    public function one(string $interface): object
    {
        $capability = $this->find($interface);

        if ($capability !== null) {
            return $capability;
        }

        throw new \RuntimeException(sprintf('Fixture capability %s is missing in %s.', $interface, $this->directory));
    }

    /** @template T of object @param class-string<T> $interface @return T|null */
    public function find(string $interface): ?object
    {
        foreach ($this->capabilities as $capability) {
            if ($capability instanceof $interface) {
                return $capability;
            }
        }

        return null;
    }
}
