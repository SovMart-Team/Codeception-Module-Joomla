<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Contract\IdentityFixtureInterface;
use JoomlaCodeception\Contract\IdentityProvisionerInterface;
use JoomlaCodeception\Dto\JoomlaClient;

final readonly class IdentityManager implements IdentityProvisionerInterface
{
    public function __construct(
        private \PDO $database,
        private DatabaseFixtureManager $fixtures,
        private CleanupJournal $cleanupJournal,
        private string $componentAsset,
        private string $projectRoot,
        private int $apiParentGroupId = 8,
        private string $identityPrefix = 'joomla-codeception',
    ) {
    }

    /** @return array<string, mixed> */
    public function create(JoomlaClient $client, IdentityFixtureInterface $fixture): array
    {
        $parentId = match ($client) {
            JoomlaClient::Administrator => 1,
            JoomlaClient::Api           => $this->apiParentGroupId,
            JoomlaClient::Site          => 2,
        };
        $groups   = $this->fixtures->table('#__usergroups');
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(sprintf('SELECT `rgt` FROM `%s` WHERE `id` = :id FOR UPDATE', $groups));
            $statement->execute(['id' => $parentId]);
            $parentRight = (int) $statement->fetchColumn();

            if ($parentRight <= 0) {
                throw new \RuntimeException(sprintf('Joomla parent user group %d is missing.', $parentId));
            }

            $statement = $this->database->prepare(sprintf('UPDATE `%s` SET `rgt` = `rgt` + 2 WHERE `rgt` >= :right', $groups));
            $statement->execute(['right' => $parentRight]);
            $statement = $this->database->prepare(sprintf('UPDATE `%s` SET `lft` = `lft` + 2 WHERE `lft` > :right', $groups));
            $statement->execute(['right' => $parentRight]);
            $statement = $this->database->prepare(sprintf(
                'INSERT INTO `%s` (`parent_id`, `lft`, `rgt`, `title`) VALUES (:parent, :left, :right, :title)',
                $groups,
            ));
            $statement->execute([
                'parent' => $parentId,
                'left'   => $parentRight,
                'right'  => $parentRight + 1,
                'title'  => $this->identityPrefix . ' ' . $client->value . ' ' . bin2hex(random_bytes(8)),
            ]);
            $groupId         = (int) $this->database->lastInsertId();
            $rootBefore      = $this->assetRules('root.1');
            $componentBefore = $this->assetRules($this->componentAsset);
            $rootRules       = $rootBefore;
            $componentRules  = $componentBefore;
            $loginPermission = match ($client) {
                JoomlaClient::Administrator => 'core.login.admin',
                JoomlaClient::Api           => 'core.login.api',
                JoomlaClient::Site          => 'core.login.site',
            };
            $rootRules[$loginPermission][(string) $groupId] = 1;

            foreach ($fixture->rootPermissions() as $permission => $value) {
                $rootRules[$permission][(string) $groupId] = $value;
            }

            foreach ($fixture->componentPermissions() as $permission => $value) {
                $componentRules[$permission][(string) $groupId] = $value;
            }

            $this->writeAssetRules('root.1', $rootRules);
            $this->writeAssetRules($this->componentAsset, $componentRules);
            $identityName = $this->identityPrefix . '-' . $client->value . '-' . bin2hex(random_bytes(10));
            $email        = $identityName . '@example.test';
            $username     = $client === JoomlaClient::Site ? $email : $identityName;
            $password     = 'password';
            $statement    = $this->database->prepare(sprintf(
                'INSERT INTO `%s` '
                . '(`name`, `username`, `email`, `password`, `block`, `sendEmail`, `registerDate`, `activation`, `params`, `resetCount`, `otpKey`, `otep`, `requireReset`, `authProvider`) '
                . 'VALUES (:name, :username, :email, :password, 0, 0, NOW(), :activation, :params, 0, :otpKey, :otep, 0, :authProvider)',
                $this->fixtures->table('#__users'),
            ));
            $statement->execute([
                'name'         => $this->identityPrefix . ' method fixture',
                'username'     => $username,
                'email'        => $email,
                'password'     => password_hash($password, PASSWORD_BCRYPT),
                'activation'   => '',
                'params'       => '{}',
                'otpKey'       => '',
                'otep'         => '',
                'authProvider' => '',
            ]);
            $userId = (int) $this->database->lastInsertId();
            $this->database->prepare(sprintf(
                'INSERT INTO `%s` (`user_id`, `group_id`) VALUES (:userId, :groupId)',
                $this->fixtures->table('#__user_usergroup_map'),
            ))->execute(['userId' => $userId, 'groupId' => $groupId]);

            $apiToken = '';

            if ($client === JoomlaClient::Api) {
                $token = (new JoomlaApiTokenFactory())->create($userId, $this->projectRoot);
                $this->insertApiTokenProfiles($userId, $token['profileToken']);
                $apiToken = $token['bearerToken'];
            }

            $this->database->commit();
        } catch (\Throwable $throwable) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $throwable;
        }

        $this->cleanupJournal->register('identity ' . $username, function () use (
            $userId,
            $username,
            $groupId,
            $parentRight,
            $rootBefore,
            $componentBefore,
            $groups,
        ): void {
            $this->remove($userId, $username, $groupId, $parentRight, $rootBefore, $componentBefore, $groups);
        });

        return compact('userId', 'username', 'email', 'password', 'groupId', 'apiToken');
    }

    private function insertApiTokenProfiles(int $userId, string $profileToken): void
    {
        $statement = $this->database->prepare(sprintf(
            'INSERT INTO `%s` (`user_id`, `profile_key`, `profile_value`, `ordering`) VALUES '
            . '(:tokenUserId, :tokenKey, :tokenValue, :tokenOrdering), '
            . '(:enabledUserId, :enabledKey, :enabledValue, :enabledOrdering)',
            $this->fixtures->table('#__user_profiles'),
        ));
        $statement->execute([
            'tokenUserId'     => $userId,
            'tokenKey'        => 'joomlatoken.token',
            'tokenValue'      => $profileToken,
            'tokenOrdering'   => 1,
            'enabledUserId'   => $userId,
            'enabledKey'      => 'joomlatoken.enabled',
            'enabledValue'    => '1',
            'enabledOrdering' => 2,
        ]);
    }

    /** @param array<string, mixed> $rootBefore @param array<string, mixed> $componentBefore */
    private function remove(
        int $userId,
        string $username,
        int $groupId,
        int $parentRight,
        array $rootBefore,
        array $componentBefore,
        string $groups,
    ): void {
        $this->database->beginTransaction();

        try {
            $this->database->prepare(sprintf(
                'DELETE FROM `%s` WHERE `user_id` = :username OR `user_id` = :userId',
                $this->fixtures->table('#__user_keys'),
            ))->execute(['username' => $username, 'userId' => (string) $userId]);
            $this->database->prepare(sprintf('DELETE FROM `%s` WHERE `userid` = :id', $this->fixtures->table('#__session')))
                ->execute(['id' => $userId]);
            $this->database->prepare(sprintf('DELETE FROM `%s` WHERE `user_id` = :id', $this->fixtures->table('#__user_profiles')))
                ->execute(['id' => $userId]);
            $this->database->prepare(sprintf('DELETE FROM `%s` WHERE `user_id` = :id', $this->fixtures->table('#__user_usergroup_map')))
                ->execute(['id' => $userId]);
            $this->database->prepare(sprintf('DELETE FROM `%s` WHERE `id` = :id', $this->fixtures->table('#__users')))
                ->execute(['id' => $userId]);
            $this->writeAssetRules('root.1', $rootBefore);
            $this->writeAssetRules($this->componentAsset, $componentBefore);
            $this->database->prepare(sprintf('DELETE FROM `%s` WHERE `id` = :id', $groups))->execute(['id' => $groupId]);
            $right     = $parentRight + 1;
            $statement = $this->database->prepare(sprintf('UPDATE `%s` SET `lft` = `lft` - 2 WHERE `lft` > :right', $groups));
            $statement->execute(['right' => $right]);
            $statement = $this->database->prepare(sprintf('UPDATE `%s` SET `rgt` = `rgt` - 2 WHERE `rgt` > :right', $groups));
            $statement->execute(['right' => $right]);
            $this->database->commit();
        } catch (\Throwable $throwable) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $throwable;
        }
    }

    /** @return array<string, mixed> */
    private function assetRules(string $name): array
    {
        $statement = $this->database->prepare(sprintf(
            'SELECT `rules` FROM `%s` WHERE `name` = :name',
            $this->fixtures->table('#__assets'),
        ));
        $statement->execute(['name' => $name]);
        $rules = json_decode((string) $statement->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($rules)) {
            throw new \RuntimeException(sprintf('Invalid Joomla ACL rules for %s.', $name));
        }

        return $rules;
    }

    /** @param array<string, mixed> $rules */
    private function writeAssetRules(string $name, array $rules): void
    {
        $statement = $this->database->prepare(sprintf(
            'UPDATE `%s` SET `rules` = :rules WHERE `name` = :name',
            $this->fixtures->table('#__assets'),
        ));
        $statement->execute(['rules' => json_encode($rules, JSON_THROW_ON_ERROR), 'name' => $name]);
    }
}
