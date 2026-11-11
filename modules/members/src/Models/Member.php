<?php

namespace Tessera\Members\Models;

use App\Models\Team;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Tessera\Members\Database\Factories\MemberFactory;

/**
 * A person the organization keeps a record of. Not a user account: a member
 * cannot sign in, and a user is not a member unless someone adds them.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $joined_on
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $team
 */
#[Fillable(['team_id', 'name', 'email', 'phone', 'joined_on', 'notes'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected $table = 'members_members';

    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Limit the query to one organization's members.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
        ];
    }
}
