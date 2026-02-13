<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\MentorshipCoMentor;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class ManageMentorshipCoMentors extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-co-mentors';
    public Training $record;

    public function mount(int|string $record): void {
        $this->record = Training::where('type', 'facility_mentorship')
                ->with(['mentor', 'coMentors'])
                ->findOrFail($this->record->id);
    }

    public function getTitle(): string {
        return "Co-Mentors - {$this->record->program->name}";
    }

    public function getSubheading(): ?string {
        $accepted = $this->record->coMentors()->where('status', 'accepted')->count();
        $pending = $this->record->coMentors()->where('status', 'pending')->count();

        return "Facility: {$this->record->facility->name} • {$accepted} active co-mentors • {$pending} pending invitations";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('invite_co_mentor')
                    ->label('Invite Co-Mentor')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('user_id')
                        ->label('Select Mentor')
                        ->options(function () {
                            $existingCoMentorIds = $this->record->coMentors()
                                    ->whereIn('status', ['pending', 'accepted'])
                                    ->pluck('user_id')
                                    ->toArray();
                            $existingCoMentorIds[] = $this->record->mentor_id;

                            return User::whereNotIn('id', $existingCoMentorIds)
                                            ->where('status', 'active')
                                            ->orderBy('first_name')
                                            ->get()
                                            ->mapWithKeys(fn($user) => [
                                                $user->id => $user->full_name .
                                                ' (' . ($user->facility?->name ?? 'No Facility') . ') - ' .
                                                ($user->cadre?->name ?? 'No Cadre'),
                            ]);
                        })
                        ->required()
                        ->searchable()
                        ->helperText('Select a user to invite as co-mentor'),
                        Forms\Components\Textarea::make('invitation_message')
                        ->label('Invitation Message (Optional)')
                        ->rows(3)
                        ->placeholder('Add a personal message to the invitation email'),
                    ])
                    ->action(fn(array $data) => $this->inviteCoMentor($data)),
                    Actions\Action::make('back')
                    ->label('Back to Mentorship')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                MentorshipCoMentor::query()
                                ->where('training_id', $this->record->id)
                                ->with(['user.facility', 'user.cadre', 'inviter'])
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('user.full_name')
                            ->label('Co-Mentor Name')
                            ->searchable(['first_name', 'last_name'])
                            ->weight('bold')
                            ->description(fn(MentorshipCoMentor $record): string =>
                                    ($record->user->cadre?->name ?? '') . ' • ' .
                                    ($record->user->facility?->name ?? 'No Facility')
                            ),
                            Tables\Columns\TextColumn::make('user.email')
                            ->label('Email')
                            ->searchable()
                            ->copyable()
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('user.phone')
                            ->label('Phone')
                            ->searchable()
                            ->copyable()
                            ->toggleable(),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'warning' => 'pending',
                                'success' => 'accepted',
                                'danger' => 'declined',
                                'gray' => 'revoked',
                                'secondary' => 'removed',
                            ])
                            ->formatStateUsing(fn(string $state): string => ucfirst($state)),
                            Tables\Columns\TextColumn::make('inviter.full_name')
                            ->label('Invited By')
                            ->searchable(['first_name', 'last_name'])
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('invited_at')
                            ->label('Invited')
                            ->dateTime('M j, Y')
                            ->sortable(),
                            Tables\Columns\TextColumn::make('accepted_at')
                            ->label('Accepted')
                            ->dateTime('M j, Y')
                            ->placeholder('Not accepted')
                            ->toggleable(),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'accepted' => 'Accepted',
                                'declined' => 'Declined',
                                'revoked' => 'Revoked',
                                'removed' => 'Removed',
                            ])
                            ->multiple(),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                // Copy invitation link (for pending invitations)
                                Tables\Actions\Action::make('copy_invite_link')
                                ->label('Copy Invite Link')
                                ->icon('heroicon-o-clipboard-document')
                                ->color('info')
                                ->visible(fn(MentorshipCoMentor $record) =>
                                        $record->status === 'pending' && $record->invitation_token
                                )
                                ->action(function (MentorshipCoMentor $record) {
                                    $link = route('co-mentor.invitation', ['token' => $record->invitation_token]);
                                    Notification::make()
                                            ->success()
                                            ->title('Invitation Link')
                                            ->body($link)
                                            ->send();
                                }),
                                // Accept (only visible to the invited user themselves)
                                Tables\Actions\Action::make('accept')
                                ->label('Accept')
                                ->icon('heroicon-o-check')
                                ->color('success')
                                ->visible(fn(MentorshipCoMentor $record) =>
                                        $record->status === 'pending' &&
                                        $record->user_id === auth()->id()
                                )
                                ->requiresConfirmation()
                                ->action(function (MentorshipCoMentor $record) {
                                    $record->accept();
                                    Notification::make()
                                            ->success()
                                            ->title('Invitation Accepted')
                                            ->body('You are now a co-mentor for this mentorship.')
                                            ->send();
                                }),
                                // Decline (only visible to the invited user themselves)
                                Tables\Actions\Action::make('decline')
                                ->label('Decline')
                                ->icon('heroicon-o-x-mark')
                                ->color('danger')
                                ->visible(fn(MentorshipCoMentor $record) =>
                                        $record->status === 'pending' &&
                                        $record->user_id === auth()->id()
                                )
                                ->requiresConfirmation()
                                ->action(function (MentorshipCoMentor $record) {
                                    $record->decline();
                                    Notification::make()
                                            ->warning()
                                            ->title('Invitation Declined')
                                            ->send();
                                }),
                                // Revoke (lead mentor can revoke pending invitations)
                                Tables\Actions\Action::make('revoke')
                                ->label('Revoke Invitation')
                                ->icon('heroicon-o-no-symbol')
                                ->color('warning')
                                ->visible(fn(MentorshipCoMentor $record) =>
                                        $record->status === 'pending' &&
                                        auth()->id() === $this->record->mentor_id
                                )
                                ->requiresConfirmation()
                                ->modalDescription('Revoke this invitation? The co-mentor will no longer be able to accept it.')
                                ->action(function (MentorshipCoMentor $record) {
                                    $record->update(['status' => 'revoked']);
                                    Notification::make()
                                            ->warning()
                                            ->title('Invitation Revoked')
                                            ->send();
                                }),
                                // Remove (lead mentor can remove accepted co-mentors)
                                Tables\Actions\Action::make('remove')
                                ->label('Remove')
                                ->icon('heroicon-o-trash')
                                ->color('danger')
                                ->visible(fn(MentorshipCoMentor $record) =>
                                        $record->status === 'accepted' &&
                                        auth()->id() === $this->record->mentor_id
                                )
                                ->requiresConfirmation()
                                ->modalDescription('Remove this co-mentor from the mentorship?')
                                ->action(function (MentorshipCoMentor $record) {
                                    $record->remove();
                                    Notification::make()
                                            ->warning()
                                            ->title('Co-Mentor Removed')
                                            ->send();
                                }),
                                // Resend invitation
                                Tables\Actions\Action::make('resend_invitation')
                                ->label('Resend Invitation')
                                ->icon('heroicon-o-envelope')
                                ->color('info')
                                ->visible(fn(MentorshipCoMentor $record) => $record->status === 'pending')
                                ->action(function (MentorshipCoMentor $record) {
                                    // TODO: Send invitation email with link
                                    $link = route('co-mentor.invitation', ['token' => $record->invitation_token]);
                                    Notification::make()
                                            ->success()
                                            ->title('Invitation Resent')
                                            ->body('Invitation email sent to ' . $record->user->email)
                                            ->send();
                                }),
                            ]),
                        ])
                        ->defaultSort('invited_at', 'desc')
                        ->emptyStateHeading('No Co-Mentors Yet')
                        ->emptyStateDescription('Invite other mentors to collaborate on this mentorship program.')
                        ->emptyStateIcon('heroicon-o-user-group')
                        ->emptyStateActions([
                            Tables\Actions\Action::make('invite_first')
                            ->label('Invite Co-Mentor')
                            ->icon('heroicon-o-plus')
                            ->button()
                            ->action(function () {
                                $this->mountAction('invite_co_mentor');
                            }),
        ]);
    }

    /**
     * Invite a co-mentor with a generated invitation_token.
     */
    private function inviteCoMentor(array $data): void {
        $token = Str::random(32);

        $coMentor = MentorshipCoMentor::create([
            'training_id' => $this->record->id,
            'user_id' => $data['user_id'],
            'invited_by' => auth()->id(),
            'invited_at' => now(),
            'status' => 'pending',
            'invitation_token' => $token,
            'permissions' => [
                'can_facilitate' => true,
                'can_create_classes' => false,
                'can_invite_mentors' => false,
            ],
        ]);

        $user = User::find($data['user_id']);
        $inviteLink = route('co-mentor.invitation', ['token' => $token]);

        // TODO: Send invitation email
        // Mail::to($user->email)->send(new CoMentorInvitation($this->record, $coMentor, $inviteLink, $data['invitation_message'] ?? null));

        Notification::make()
                ->success()
                ->title('Invitation Sent')
                ->body("Co-mentor invitation sent to {$user->full_name}. Link: {$inviteLink}")
                ->send();
    }
}
