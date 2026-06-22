<?php

namespace App\Services;

use App\Mail\EmoncNotificationMail;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class EmoncNotificationService
{
    public function activityCompleted(ClassParticipant $participant, ClassModule $classModule): void
    {
        $user = $participant->user;
        if (! $user) {
            return;
        }

        $moduleName = $classModule->programModule?->name ?? 'a module';

        $this->notify(
            $user,
            'Activity Completed',
            "Activity Completed — {$moduleName}",
            "All activities for {$moduleName} have been marked as completed. Your module progress is now complete.",
            route('mentee.class.progress', $classModule->mentorship_class_id),
            'View My Progress'
        );
    }

    public function videoSubmitted(MenteeModuleProgress $progress): void
    {
        $classModule = $progress->classModule;
        $training = $classModule?->mentorshipClass?->training;

        if (! $training) {
            return;
        }

        $mentor = $training->mentor;
        $moduleName = $classModule->programModule?->name ?? 'a module';
        $menteeName = $progress->classParticipant?->user?->name ?? 'A mentee';

        $recipients = collect([$mentor])->filter();
        $coMentors = $training->coMentors()
            ->where('status', 'accepted')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        $recipients = $recipients->merge($coMentors);

        foreach ($recipients as $recipient) {
            $this->notify(
                $recipient,
                'Hands-on Video Submitted',
                'Video Ready for Review',
                "{$menteeName} has submitted a hands-on video for {$moduleName}. Please review it when ready.",
                route('mentee.class.progress', $classModule->mentorship_class_id),
                'Review Video'
            );
        }
    }

    public function videoReviewed(MenteeModuleProgress $progress): void
    {
        $user = $progress->classParticipant?->user;
        if (! $user) {
            return;
        }

        $classModule = $progress->classModule;
        $moduleName = $classModule?->programModule?->name ?? 'a module';
        $status = $progress->video_review_status === 'passed' ? 'passed' : 'did not pass';
        $notes = $progress->video_review_notes ? " Mentor notes: {$progress->video_review_notes}" : '';

        $this->notify(
            $user,
            'Video Review Result',
            "Video Review — {$status}",
            "Your hands-on video for {$moduleName} has been reviewed and {$status}.{$notes}",
            route('mentee.class.module', [$classModule->mentorship_class_id, $classModule->id]),
            'View Module'
        );
    }

    public function mentorApproved(ClassParticipant $participant): void
    {
        $user = $participant->user;
        if (! $user) {
            return;
        }

        $class = $participant->mentorshipClass;

        $this->notify(
            $user,
            'Mentor Approval Received',
            'Approved for Certification',
            "Your mentor has approved you for certification for {$class->name}. The Head DRMH will now review and certify you.",
            route('mentee.class.progress', $class->id),
            'View Progress'
        );

        // Notify Head DRMH users
        $headDrmhUsers = User::whereHas('roles', fn ($q) => $q->where('name', 'head_drmh'))->get();
        foreach ($headDrmhUsers as $headDrmh) {
            $this->notify(
                $headDrmh,
                'Certification Pending',
                'Mentee Awaiting Certification',
                "{$user->name} has been mentor-approved for {$class->name} and is awaiting Head DRMH certification.",
                route('filament.admin.resources.mentorship-training-resource.mentees', ['record' => $class->training_id]),
                'Review Mentee'
            );
        }
    }

    public function headDrmhCertified(ClassParticipant $participant): void
    {
        $user = $participant->user;
        if (! $user) {
            return;
        }

        $class = $participant->mentorshipClass;

        $this->notify(
            $user,
            'Certificate Issued',
            'You Are Certified',
            "Congratulations! You have been certified for {$class->name}. You can now download your certificate.",
            route('reports.reports.class.certificate', [$class->id, $participant->id]),
            'Download Certificate'
        );
    }

    private function notify(User $user, string $databaseTitle, string $emailSubject, string $emailMessage, ?string $actionUrl, ?string $actionText): void
    {
        // In-app notification
        Notification::make()
            ->title($databaseTitle)
            ->body(strip_tags($emailMessage))
            ->success()
            ->sendToDatabase($user);

        // Email notification
        if (! empty($user->email)) {
            try {
                Mail::to($user->email)
                    ->send(new EmoncNotificationMail($user, $emailSubject, $emailSubject, $emailMessage, $actionUrl, $actionText));
            } catch (\Throwable $e) {
                // Fail silently — don't block business logic for email errors
                report($e);
            }
        }
    }
}
