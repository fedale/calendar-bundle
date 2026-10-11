<?php

namespace Fedale\CalendarBundle\Tests\Integration\Fixtures;

use Fedale\SettingBundle\Contract\SettingStorageInterface;

/** setting-bundle's free-form settings, unused here: no Doctrine in the test kernel. */
final class NoSettingStorage implements SettingStorageInterface
{
    public function loadTenant(int $tenantId): array
    {
        return [];
    }

    public function upsert(int $tenantId, string $name, mixed $value, string $type, bool $active): void
    {
    }
}
