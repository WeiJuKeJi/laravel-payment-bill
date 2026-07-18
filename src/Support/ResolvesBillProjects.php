<?php

namespace WeiJuKeJi\PaymentBill\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

trait ResolvesBillProjects
{
    /** @var array<string, bool> */
    protected static array $resolvedProjectColumnCache = [];

    protected function applyResolvedProjectFilters(Builder $query, mixed $projectId, mixed $hasProject): void
    {
        if ($this->hasResolvedProjectColumn($query)) {
            if (filled($projectId)) {
                $query->where($query->getModel()->qualifyColumn('resolved_project_id'), (int) $projectId);

                return;
            }

            if ($hasProject === true) {
                $query->whereNotNull($query->getModel()->qualifyColumn('resolved_project_id'));

                return;
            }

            if ($hasProject === false) {
                $query->whereNull($query->getModel()->qualifyColumn('resolved_project_id'));
            }

            return;
        }

        $resolverClass = config('payment-bill.project_resolver');

        if (! is_string($resolverClass) || $resolverClass === '' || ! class_exists($resolverClass)) {
            return;
        }

        $resolver = app($resolverClass);
        if (method_exists($resolver, 'applyFilters')) {
            $resolver->applyFilters(
                $query,
                filled($projectId) ? (int) $projectId : null,
                is_bool($hasProject) ? $hasProject : null
            );
        }
    }

    protected function attachResolvedProjects(Collection $bills): void
    {
        $resolverClass = config('payment-bill.project_resolver');

        if (! is_string($resolverClass) || $resolverClass === '' || ! class_exists($resolverClass)) {
            return;
        }

        $projects = app($resolverClass)->resolve($bills);

        foreach ($bills as $bill) {
            $bill->setAttribute('resolved_project', $projects[(string) $bill->getKey()] ?? null);
        }
    }

    protected function hasResolvedProjectColumn(Builder $query): bool
    {
        if (! config('payment-bill.project_cache_filtering_enabled', false)) {
            return false;
        }

        $model = $query->getModel();
        $cacheKey = $model->getConnectionName().'|'.$model->getTable();

        return self::$resolvedProjectColumnCache[$cacheKey]
            ??= Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), 'resolved_project_id');
    }
}
