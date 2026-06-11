<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Holds the active company for the current request/process. Resolved from the
 * authenticated user's company switcher in the UI; set explicitly in Actions,
 * seeders and tests. The global CompanyScope reads from here. (§2)
 */
final class CompanyContext
{
    private ?int $companyId = null;

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function has(): bool
    {
        return $this->companyId !== null;
    }

    public function forget(): void
    {
        $this->companyId = null;
    }

    /**
     * Run a callback with a temporarily-overridden active company, restoring
     * the previous value afterwards.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public function runAs(int $companyId, callable $callback): mixed
    {
        $previous = $this->companyId;
        $this->companyId = $companyId;

        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
        }
    }
}
