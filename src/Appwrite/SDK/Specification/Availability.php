<?php

namespace Appwrite\SDK\Specification;

use Appwrite\SDK\AuthType;
use Appwrite\SDK\Method;
use LogicException;
use Utopia\Http\Route;
use WeakMap;

/** Effective SDK descriptor availability for one spec generation mode. */
class Availability
{
    /**
     * @param WeakMap<Method, list<string>>|null $platforms Auth memberships resolved by the active producer; null uses the public mapping.
     */
    public function __construct(private ?WeakMap $platforms = null, private bool $mocks = false)
    {
    }

    /**
     * @param array<AuthType> $auth
     * @return list<string>
     */
    public function getAuthPlatforms(array $auth): array
    {
        $platforms = [];
        foreach ($auth as $type) {
            $platform = match ($type) {
                AuthType::SESSION => APP_SDK_PLATFORM_CLIENT,
                AuthType::JWT, AuthType::KEY => APP_SDK_PLATFORM_SERVER,
                AuthType::ADMIN => APP_SDK_PLATFORM_CONSOLE,
                default => null,
            };
            if ($platform !== null) {
                $platforms[] = $platform;
            }
        }

        return $platforms;
    }

    /**
     * Without a method, return the ordered union across all eligible descriptors.
     *
     * @return list<string>
     */
    public function getPlatforms(Route $route, ?Method $method = null): array
    {
        if (!$route->getLabel('docs', true) || (bool) $route->getLabel('mock', false) !== $this->mocks) {
            return [];
        }

        if ($method === null) {
            $methods = $route->getLabel('sdk', []);
            $platforms = [];
            foreach (\is_array($methods) ? $methods : [$methods] as $descriptor) {
                if ($descriptor instanceof Method) {
                    $platforms = \array_merge($platforms, $this->getPlatforms($route, $descriptor));
                }
            }

            return \array_values(\array_unique($platforms));
        }

        $hide = $method->isHidden();
        if ($hide === true || empty($method->getNamespace())) {
            return [];
        }

        if ($this->platforms !== null && !isset($this->platforms[$method])) {
            throw new LogicException('Missing SDK auth platforms for ' . $method->getNamespace() . '.' . $method->getMethodName());
        }

        $platforms = $this->platforms === null
            ? $this->getAuthPlatforms($method->getAuth())
            : $this->platforms[$method];

        return \array_values(\array_unique(\array_diff($platforms, \is_array($hide) ? $hide : [])));
    }
}
