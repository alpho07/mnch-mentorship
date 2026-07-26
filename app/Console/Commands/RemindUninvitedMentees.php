<?php

namespace App\Console\Commands;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\MentorshipClass;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class RemindUninvitedMentees extends Command
{
    protected $signature = 'mentorships:remind-uninvited-mentees';

    protected $description = 'Notify mentors of mentorship classes that have mentees on the roster who have not been sent their enrollment invite yet';

    /**
     * Re-reminds every 3 days for a class that's still unresolved, rather
     * than only once — a mentor who ignores the bell notification should
     * still get nudged again instead of going silent forever.
     */
    private const REMINDER_COOLDOWN_DAYS = 3;

    public function handle(): int
    {
        $classes = MentorshipClass::whereIn('status', ['draft', 'active'])
            ->whereHas('participants', fn ($q) => $q->whereNull('invitation_sent_at'))
            ->with([
                'training.mentor',
                'training.acceptedCoMentors.user',
                'participants' => fn ($q) => $q->whereNull('invitation_sent_at'),
            ])
            ->get();

        $classIdsWithUninvited = $classes->pluck('id');

        // Resolved classes: had a reminder pending but every mentee is now
        // invited — reset the cooldown so a freshly-added mentee later
        // triggers a prompt reminder instead of waiting out the old cooldown.
        MentorshipClass::whereNotNull('uninvited_mentee_reminder_sent_at')
            ->whereNotIn('id', $classIdsWithUninvited)
            ->update(['uninvited_mentee_reminder_sent_at' => null]);

        $sent = 0;

        foreach ($classes as $class) {
            $training = $class->training;
            if (! $training) {
                continue;
            }

            $dueForReminder = $class->uninvited_mentee_reminder_sent_at === null
                || $class->uninvited_mentee_reminder_sent_at->lte(now()->subDays(self::REMINDER_COOLDOWN_DAYS));

            if (! $dueForReminder) {
                continue;
            }

            $recipients = collect([$training->mentor])
                ->merge($training->acceptedCoMentors->pluck('user'))
                ->filter()
                ->unique('id');

            if ($recipients->isEmpty()) {
                continue;
            }

            $uninvitedCount = $class->participants->count();
            $url = MentorshipTrainingResource::getUrl('class-mentees', [
                'training' => $training->id,
                'class' => $class->id,
            ]);

            $body = $uninvitedCount === 1
                ? "1 mentee in \"{$class->name}\" ({$training->title}) hasn't been invited yet."
                : "{$uninvitedCount} mentees in \"{$class->name}\" ({$training->title}) haven't been invited yet.";

            foreach ($recipients as $user) {
                Notification::make()
                    ->title('Mentees waiting to be invited')
                    ->body($body)
                    ->icon('heroicon-o-user-plus')
                    ->warning()
                    ->actions([
                        Action::make('view')
                            ->label('Manage Mentees')
                            ->url($url)
                            ->markAsRead(),
                    ])
                    ->sendToDatabase($user);

                $sent++;
            }

            $class->update(['uninvited_mentee_reminder_sent_at' => now()]);
        }

        $this->info("Sent {$sent} uninvited-mentee reminder(s) across {$classes->count()} class(es).");

        return Command::SUCCESS;
    }
}
