<?php

namespace TidyOrg\Inventory\Models;

use App\Models\Team;
use App\Modules\StoredFile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use TidyOrg\Inventory\Database\Factories\ItemFactory;
use TidyOrg\Inventory\Enums\ItemType;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $parent_id
 * @property ItemType $type
 * @property string $name
 * @property array<string, string>|null $metadata
 * @property string|null $photo_path
 * @property string|null $thumb_path
 * @property int|null $checked_out_to
 * @property Carbon|null $checked_out_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $truncated_name
 * @property-read string|null $photo_url
 * @property-read string|null $thumb_url
 * @property-read Team|null $team
 * @property-read Item|null $parent
 */
#[Fillable(['team_id', 'parent_id', 'type', 'name', 'metadata', 'photo_path', 'thumb_path', 'checked_out_to', 'checked_out_at'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_items';

    /**
     * The directory on the default disk that holds an organization's item photos.
     */
    public static function storageDirectory(int $teamId): string
    {
        return "teams/{$teamId}/inventory";
    }

    protected static function newFactory(): ItemFactory
    {
        return ItemFactory::new();
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Limit the query to one organization's items.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /**
     * The ids of everything nested inside this item, at any depth.
     *
     * @return Collection<int, int>
     */
    public function descendantIds(): Collection
    {
        $connection = $this->getConnection();
        $table = $connection->getQueryGrammar()->wrapTable($this->getTable());

        return collect($connection->select(
            "with recursive descendants as (
                select id from {$table} where parent_id = ?
                union all
                select {$table}.id from {$table} inner join descendants on descendants.id = {$table}.parent_id
            ) select id from descendants",
            [$this->id],
        ))->pluck('id')->map(fn (mixed $id): int => (int) $id);
    }

    /**
     * Move the item to the deleted-items view, leaving its contents at the top level.
     */
    public function purge(): void
    {
        $this->children()->update(['parent_id' => null]);
        $this->delete();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'metadata' => 'array',
            'checked_out_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function truncatedName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Str::limit($this->name, 75),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => StoredFile::url($this->photo_path),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function thumbUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => StoredFile::url($this->thumb_path ?? $this->photo_path),
        );
    }
}
