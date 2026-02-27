<x-filament-panels::page>
    {{-- Last Invitation Link Section (shown after inviting a co-mentor) --}}
    @if($lastInvitationLink)
        <div class="mb-6">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon
                        icon="heroicon-o-link"
                        class="h-5 w-5 text-success-500"
                        />
                        <span>Co-Mentor Invitation Sent</span>
                    </div>
                </x-slot>

                <x-slot name="description">
                    Share this link with <strong>{{ $lastInvitedName }}</strong> to accept the co-mentor invitation
                </x-slot>

                <div class="space-y-3">
                    {{-- Link Display Box --}}
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 font-mono text-sm text-gray-700 dark:text-gray-300 break-all">
                                {{ $lastInvitationLink }}
                            </div>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex flex-wrap gap-2">
                        {{-- Copy Button --}}
                        <x-filament::button
                        color="primary"
                        icon="heroicon-o-clipboard-document"
                        x-data="{}"
                        x-on:click="
                                navigator.clipboard.writeText('{{ $lastInvitationLink }}');
                                new FilamentNotification()
                                    .title('Link Copied!')
                                    .success()
                                    .send();
                            "
                        >
                        Copy Link
                        </x-filament::button>

                        {{-- Share Button --}}
                        <x-filament::button
                        color="info"
                        icon="heroicon-o-share"
                        x-data="{}"
                        x-on:click="
                                if (navigator.share) {
                                    navigator.share({
                                        title: 'Co-Mentor Invitation',
                                        text: 'You have been invited as a co-mentor. Click the link to accept:',
                                        url: '{{ $lastInvitationLink }}'
                                    });
                                } else {
                                    navigator.clipboard.writeText('{{ $lastInvitationLink }}');
                                    new FilamentNotification()
                                        .title('Link Copied!')
                                        .body('Share not supported. Link copied to clipboard.')
                                        .success()
                                        .send();
                                }
                            "
                        >
                        Share
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        </div>
    @endif

    {{-- Main Table --}}
    {{ $this->table }}
</x-filament-panels::page>  