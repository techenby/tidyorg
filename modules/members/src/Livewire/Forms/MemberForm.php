<?php

namespace TidyOrg\Members\Livewire\Forms;

use App\Models\Team;
use Livewire\Form;
use TidyOrg\Members\Models\Member;

class MemberForm extends Form
{
    public ?Member $editingMember = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public ?string $joined_on = null;

    public string $notes = '';

    public function load(Member $member): void
    {
        $this->reset();

        $this->fill([
            'editingMember' => $member,
            'name' => $member->name,
            'email' => (string) $member->email,
            'phone' => (string) $member->phone,
            'joined_on' => $member->joined_on?->toDateString(),
            'notes' => (string) $member->notes,
        ]);
    }

    /**
     * Add the member to the given organization, or update the one being edited.
     */
    public function save(Team $team): Member
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $attributes = [
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'joined_on' => $this->joined_on ?: null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingMember) {
            $this->editingMember->update($attributes);
            $member = $this->editingMember;
        } else {
            $member = Member::create([...$attributes, 'team_id' => $team->id]);
        }

        $this->reset();

        return $member;
    }
}
