<?php

namespace WeiJuKeJi\PaymentBill\Tests\Unit\Support;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\TestCase;
use WeiJuKeJi\PaymentBill\Models\WechatBill;
use WeiJuKeJi\PaymentBill\Support\ResolvesBillProjects;

class ResolvesBillProjectsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule;
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $container = new Container;
        $container->instance('config', new Repository([
            'payment-bill' => [
                'project_resolver' => ProjectScopeResolverStub::class,
            ],
        ]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_cached_project_filter_uses_resolved_project_scope_ids(): void
    {
        $query = WechatBill::query();

        (new ResolvesBillProjectsHarness)->apply($query, 180, null);

        self::assertStringContainsString('"resolved_project_id" in (?, ?, ?)', $query->toSql());
        self::assertSame([180, 181, 182], $query->getBindings());
    }

    public function test_cached_project_filter_falls_back_to_selected_project_id(): void
    {
        config()->set('payment-bill.project_resolver', ProjectResolverWithoutScopeStub::class);
        $query = WechatBill::query();

        (new ResolvesBillProjectsHarness)->apply($query, 180, null);

        self::assertStringContainsString('"resolved_project_id" in (?)', $query->toSql());
        self::assertSame([180], $query->getBindings());
    }
}

class ResolvesBillProjectsHarness
{
    use ResolvesBillProjects;

    public function apply(Builder $query, mixed $projectId, mixed $hasProject): void
    {
        $this->applyResolvedProjectFilters($query, $projectId, $hasProject);
    }

    protected function hasResolvedProjectColumn(Builder $query): bool
    {
        return true;
    }
}

class ProjectScopeResolverStub
{
    /** @return list<int> */
    public function resolveProjectScopeIds(int $projectId): array
    {
        return [$projectId, 181, 182];
    }
}

class ProjectResolverWithoutScopeStub {}
