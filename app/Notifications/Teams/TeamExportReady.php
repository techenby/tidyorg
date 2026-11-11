<?php

namespace App\Notifications\Teams;

use App\Console\Commands\OrganizationPruneCommand;
use App\Models\Team;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamExportReady extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Team $team, public string $file)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your export of :teamName is ready', ['teamName' => $this->team->name]))
            ->line(__('The export of everything :teamName has stored is ready to download.', ['teamName' => $this->team->name]))
            ->action(__('Download export'), route('teams.exports.download', ['team' => $this->team->slug, 'file' => $this->file]))
            ->line(__('You need to be signed in as an owner of the organization. The file is deleted after :days days.', ['days' => OrganizationPruneCommand::EXPORT_DAYS]));
    }
}
