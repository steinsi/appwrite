<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Specification;

use Appwrite\SDK\AuthType;
use Appwrite\SDK\Method;
use Appwrite\SDK\Parameter;
use Appwrite\SDK\Specification\Availability;
use Appwrite\SDK\Specification\Format\OpenAPI3;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Utopia\DI\Container;
use Utopia\Http\Route;
use Utopia\Validator\Text;
use WeakMap;

final class AvailabilityTest extends TestCase
{
    protected function setUp(): void
    {
        Method::$processed = [];
        Method::$errors = [];
    }

    public static function hiddenMethods(): \Iterator
    {
        yield 'hidden first' => [true, false, 'tests', 'tests', ['second']];
        yield 'hidden sibling' => [false, true, 'tests', 'tests', ['first']];
        yield 'all hidden' => [true, true, 'tests', 'tests', []];
        yield 'hide list' => [['server'], false, 'tests', 'tests', ['second']];
        yield 'empty first namespace' => [false, false, '', 'tests', ['second']];
        yield 'empty sibling namespace' => [false, false, 'tests', '', ['first']];
    }

    #[DataProvider('hiddenMethods')]
    public function testOnlyEligibleAliasesAreEmitted(bool|array $firstHide, bool|array $secondHide, string $firstNamespace, string $secondNamespace, array $names): void
    {
        $first = new Method($firstNamespace, null, 'first', 'First.', [AuthType::KEY], [], hide: $firstHide);
        $second = new Method($secondNamespace, null, 'second', 'Second.', [AuthType::KEY], [], hide: $secondHide);
        $route = (new Route('PATCH', '/v1/tests'))->label('sdk', [$first, $second]);

        $spec = (new OpenAPI3(new Container(), [], [$route], [], [], 0, 'server'))->parse();

        if ($names === []) {
            $this->assertArrayNotHasKey('/tests', $spec['paths']);
            return;
        }
        $operation = $spec['paths']['/tests']['patch'];
        $this->assertSame($firstNamespace . 'First', $operation['operationId']);
        $this->assertSame($names, array_column($operation['x-appwrite']['methods'], 'name'));
        $this->assertSame(['server'], $operation['x-appwrite']['platforms']);
        foreach ($operation['x-appwrite']['methods'] as $method) {
            $this->assertSame(['server'], $method['platforms']);
        }
    }

    public function testDisjointSameNameVariantsPreserveParametersRequiredAndAuth(): void
    {
        $client = new Method('presences', null, 'update', 'Update.', [AuthType::SESSION, AuthType::ADMIN], [], parameters: [new Parameter('presenceId', optional: false)]);
        $server = new Method('presences', null, 'update', 'Update.', [AuthType::KEY, AuthType::JWT], [], parameters: [new Parameter('presenceId', optional: false), new Parameter('userId', optional: false)]);
        $route = (new Route('PATCH', '/v1/presences/:presenceId'))
            ->label('scope', 'presences.write')
            ->label('sdk', [$client, $server])
            ->param('presenceId', '', new Text(36), 'Presence ID.')
            ->param('userId', '', new Text(36), 'User ID.', true);
        $keys = ['Project' => [], 'Key' => [], 'JWT' => [], 'Session' => [], 'Admin' => []];

        foreach (['client', 'server', 'console', 'client'] as $platform) {
            $spec = (new OpenAPI3(new Container(), [], [$route], [], $keys, $platform === 'server' ? 2 : 1, $platform))->parse();
            $operation = $spec['paths']['/presences/{presenceId}']['patch'];
            $method = $operation['x-appwrite']['methods'][0];

            $this->assertSame(['client', 'console', 'server'], $operation['x-appwrite']['platforms']);
            $this->assertCount(1, $operation['x-appwrite']['methods']);
            $this->assertSame('update', $method['name']);
            $this->assertSame('presences', $method['namespace']);
            $this->assertSame($platform === 'server' ? ['server'] : ['client', 'console'], $method['platforms']);
            $this->assertSame($platform === 'server' ? ['presenceId', 'userId'] : ['presenceId'], $method['parameters']);
            $this->assertSame($method['parameters'], $method['required']);
            $this->assertSame($platform === 'server' ? ['Project' => [], 'Key' => []] : ['Project' => []], $method['auth']);
            $this->assertSame($platform === 'server' ? ['Project' => [], 'Session' => []] : ['Project' => []], $operation['x-appwrite']['auth']);
            $this->assertSame([['Project' => [], 'Session' => [], 'Admin' => []]], $operation['security']);
            $body = $operation['requestBody']['content']['application/json']['schema'];
            $this->assertArrayHasKey('userId', $body['properties']);
            $this->assertNotContains('userId', $body['required'] ?? []);
        }
        $this->assertSame([$client, $server], $route->getLabel('sdk', []));
    }

    public function testPublicFalseIsNotHiddenAndOperationUnionIsDeduplicated(): void
    {
        $method = new Method('tests', null, 'get', 'Get.', [AuthType::KEY, AuthType::JWT, AuthType::SESSION], [], hide: ['client'], public: false);
        $route = (new Route('GET', '/v1/tests'))->label('sdk', [$method, new Method('tests', null, 'list', 'List.', [AuthType::KEY], [])]);

        $spec = (new OpenAPI3(new Container(), [], [$route], [], [], 0, 'server'))->parse();

        $this->assertSame(['server'], $spec['paths']['/tests']['get']['x-appwrite']['platforms']);
        $this->assertFalse($spec['paths']['/tests']['get']['x-appwrite']['public']);
        $this->assertFalse($spec['paths']['/tests']['get']['x-appwrite']['methods'][0]['public']);
    }

    public function testCustomAuthMembershipIsUsedWithoutChangingAuth(): void
    {
        $method = new Method('tests', null, 'get', 'Get.', [AuthType::KEY, AuthType::ADMIN], []);
        $route = (new Route('GET', '/v1/tests'))->label('scope', 'tests.read')->label('sdk', [$method]);
        $platforms = new WeakMap();
        $platforms[$method] = ['manager', 'manager'];
        $availability = new Availability($platforms, mocks: false);

        $spec = (new OpenAPI3(new Container(), [], [$route], [], ['Key' => []], 1, 'manager', $availability))->parse();
        $operation = $spec['paths']['/tests']['get'];

        $this->assertSame(['manager'], $operation['x-appwrite']['platforms']);
        $this->assertSame(['manager'], $operation['x-appwrite']['methods'][0]['platforms']);
        $this->assertSame(['Project' => []], $operation['x-appwrite']['methods'][0]['auth']);
        $this->assertSame([['Project' => [], 'Key' => []]], $operation['security']);
        $platforms[$method] = [];
        $this->assertSame([], $availability->getPlatforms($route));
    }

    public function testMissingCustomMembershipDoesNotFallBack(): void
    {
        $method = new Method('tests', null, 'get', 'Get.', [AuthType::KEY], []);
        $route = (new Route('GET', '/v1/tests'))->label('sdk', $method);
        $availability = new Availability(new WeakMap(), mocks: false);

        $this->expectException(\LogicException::class);
        $availability->getPlatforms($route);
    }

    public function testAuthMembershipKeepsRolesOrderAndDuplicates(): void
    {
        $availability = new Availability();

        $this->assertSame(['server', 'client', 'server', 'console'], $availability->getAuthPlatforms([
            AuthType::JWT,
            AuthType::SESSION,
            AuthType::KEY,
            AuthType::ADMIN,
            AuthType::ORGANIZATION,
        ]));
        $this->assertSame([], $availability->getAuthPlatforms([]));
        $this->assertSame([], $availability->getAuthPlatforms([AuthType::ORGANIZATION]));
    }

    public function testRouteEligibilityUsesDocsAndMockMode(): void
    {
        $method = new Method('tests', null, 'get', 'Get.', [AuthType::KEY], []);
        $route = (new Route('GET', '/v1/tests'))->label('sdk', $method);
        $normal = new Availability(mocks: false);
        $mocks = new Availability(mocks: true);

        $this->assertSame(['server'], $normal->getPlatforms($route));
        $this->assertSame([], $mocks->getPlatforms($route));
        $route->label('mock', true);
        $this->assertSame([], $normal->getPlatforms($route));
        $this->assertSame(['server'], $mocks->getPlatforms($route));
        $route->label('docs', false);
        $this->assertSame([], $mocks->getPlatforms($route));
    }
}
