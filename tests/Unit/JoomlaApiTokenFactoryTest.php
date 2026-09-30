<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

use JoomlaCodeception\Service\JoomlaApiTokenFactory;
use JoomlaCodeception\Exception\UnsupportedEnvironmentException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[PreserveGlobalState(false)]
final class JoomlaApiTokenFactoryTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/joomla-api-token-' . bin2hex(random_bytes(8));
        mkdir($this->projectRoot, 0700, true);
    }

    protected function tearDown(): void
    {
        $configuration = $this->projectRoot . '/configuration.php';

        if (is_file($configuration)) {
            unlink($configuration);
        }

        if (is_dir($this->projectRoot)) {
            rmdir($this->projectRoot);
        }
    }

    #[RunInSeparateProcess]
    public function testCreatesJoomlaCompatibleProfileAndBearerTokens(): void
    {
        $secret = 'unit-test-secret';
        $this->writeConfiguration($secret);

        $tokens = (new JoomlaApiTokenFactory())->create(17, $this->projectRoot);
        $seed   = base64_decode($tokens['profileToken'], true);

        self::assertIsString($seed);
        self::assertSame(32, strlen($seed));
        self::assertSame(
            'sha256:17:' . hash_hmac('sha256', $seed, $secret),
            base64_decode($tokens['bearerToken'], true),
        );
    }

    public function testRejectsNonPositiveUserIdBeforeReadingConfiguration(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('user ID must be positive');

        (new JoomlaApiTokenFactory())->create(0, $this->projectRoot);
    }

    public function testRejectsMissingConfiguration(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('configuration file is not readable');

        (new JoomlaApiTokenFactory())->create(17, $this->projectRoot);
    }

    #[RunInSeparateProcess]
    public function testRejectsEmptySiteSecret(): void
    {
        $this->writeConfiguration('');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('site secret must not be empty');

        (new JoomlaApiTokenFactory())->create(17, $this->projectRoot);
    }

    #[RunInSeparateProcess]
    public function testRejectsConfigurationLoadedFromAnotherJoomlaRoot(): void
    {
        $otherRoot = sys_get_temp_dir() . '/joomla-api-token-other-' . bin2hex(random_bytes(8));
        mkdir($otherRoot, 0700, true);
        file_put_contents($otherRoot . '/configuration.php', "<?php\nclass JConfig { public string \$secret = 'other'; }\n");
        require $otherRoot . '/configuration.php';
        $this->writeConfiguration('expected');

        try {
            $this->expectException(UnsupportedEnvironmentException::class);
            $this->expectExceptionMessage('another Joomla root');
            (new JoomlaApiTokenFactory())->create(17, $this->projectRoot);
        } finally {
            unlink($otherRoot . '/configuration.php');
            rmdir($otherRoot);
        }
    }

    private function writeConfiguration(string $secret): void
    {
        $source = "<?php\nclass JConfig { public string \$secret = " . var_export($secret, true) . "; }\n";
        file_put_contents($this->projectRoot . '/configuration.php', $source);
    }
}
