<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

/**
 * The reporting dimensions on a document: department, project, fund and
 * branch, as searchable "code — name" pickers on forms and a Tags section on
 * view pages. A document's tags apply to every line it posts.
 */
final class DimensionSelects
{
    private const DIMENSIONS = [
        'department_id' => ['Department', Department::class, 'department'],
        'project_id' => ['Project', Project::class, 'project'],
        'fund_id' => ['Fund', Fund::class, 'fund'],
        'branch_id' => ['Branch', Branch::class, 'branch'],
    ];

    /**
     * @return list<Select>
     */
    public static function make(): array
    {
        $selects = [];
        foreach (self::DIMENSIONS as $field => [$label, $model]) {
            $selects[] = Select::make($field)->label($label)
                ->options(fn (): array => $model::query()->where('is_active', true)->orderBy('code')->get()
                    ->mapWithKeys(fn (Model $record): array => [$record->getKey() => $record->getAttribute('code').' — '.$record->getAttribute('name')])->all())
                ->searchable()
                ->placeholder('—');
        }

        return $selects;
    }

    /** The form section: collapsed unless a tag is set. */
    public static function section(): Section
    {
        return Section::make('Tags')
            ->description('Department, project, fund and branch for reporting. They apply to every line.')
            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->columnSpanFull()
            ->collapsible()
            ->schema(self::make());
    }

    /** The Tags section of a view page, shown only when something is tagged. */
    public static function infolistSection(): Section
    {
        $entries = [];
        foreach (self::DIMENSIONS as $field => [$label, $model, $relation]) {
            $entries[] = TextEntry::make("{$relation}.name")->label($label)
                ->formatStateUsing(fn (Model $record): string => $record->getRelation($relation)?->getAttribute('code').' · '.$record->getRelation($relation)?->getAttribute('name'))
                ->placeholder('—');
        }

        return Section::make('Tags')
            ->columns(4)
            ->visible(fn (Model $record): bool => array_filter(array_map(fn (string $field): mixed => $record->getAttribute($field), array_keys(self::DIMENSIONS))) !== [])
            ->schema($entries);
    }
}
