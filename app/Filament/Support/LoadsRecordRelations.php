<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * For view pages whose infolist reads relations of the record's lines. Livewire
 * re-fetches the record without its relations on every request after the
 * first (an action, say), and lines loaded lazily after that trip the
 * lazy-loading guard, so the relations are loaded again before each render.
 */
trait LoadsRecordRelations
{
    /**
     * @return list<string>
     */
    abstract protected function recordRelations(): array;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load($this->recordRelations());
    }

    public function renderingLoadsRecordRelations(): void
    {
        $this->getRecord()->loadMissing($this->recordRelations());
    }
}
