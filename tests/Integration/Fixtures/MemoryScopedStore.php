<?php

namespace Fedale\CalendarBundle\Tests\Integration\Fixtures;

use Fedale\SettingBundle\Scoped\Scope;
use Fedale\SettingBundle\Scoped\ScopeChain;
use Fedale\SettingBundle\Scoped\Store\ScopedStoreInterface;

/**
 * Scoped settings kept in a static array: the test client reboots the kernel
 * between requests, the values must survive it. Tests reset it in setUp().
 */
final class MemoryScopedStore implements ScopedStoreInterface
{
    /** @var array<string, array<int, array<string, array<string, string>>>> namespace => tenant => scope => key => value */
    public static array $data = [];

    public function load(string $namespace, ScopeChain $chain, array $tenantIds): array
    {
        $result = [];
        foreach ($tenantIds as $tenantId) {
            foreach ($chain->scopes as $scope) {
                if (($bag = self::$data[$namespace][$tenantId][(string) $scope] ?? []) !== []) {
                    $result[$tenantId][(string) $scope] = $bag;
                }
            }
        }

        return $result;
    }

    public function save(string $namespace, Scope $scope, int $tenantId, array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                unset(self::$data[$namespace][$tenantId][(string) $scope][$key]);
            } else {
                self::$data[$namespace][$tenantId][(string) $scope][$key] = $value;
            }
        }
    }
}
