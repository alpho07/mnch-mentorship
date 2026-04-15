<?php

namespace App\Mail;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MenteeEnrollmentInvitationMail extends Mailable {

    use Queueable,
        SerializesModels;

    public string $enrollmentLink;
    public bool $isResend;

    public function __construct(
            public readonly User $mentee,
            public readonly MentorshipClass $class,
            public readonly ClassParticipant $participant,
            bool $isResend = false,
    ) {
        $this->isResend = $isResend;
        $this->enrollmentLink = route('mentee.enroll', ['token' => $class->enrollment_token]);
    }

    public function envelope(): Envelope {
        $subject = $this->isResend ? "Reminder: Your Enrollment Link — {$this->class->name}" : "You've been invited to join {$this->class->name}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content {
        return new Content(
                view: 'emails.mentee-enrollment-invitation',
        );
    }
}
