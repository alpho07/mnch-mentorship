<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end gap-x-3">
            <x-filament::button type="submit">
                Save Assessments
            </x-filament::button>
        </div>
    </form>

    {{-- Alternative Matrix View --}}
    <div class="mt-8">
        <h3 class="text-lg font-medium">Assessment Matrix View</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            Participant
                        </th>
                        @foreach($objectives as $objective)
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                {{ Str::limit($objective->objective_text, 30) }}
                                <br>
                                <span class="text-xs text-gray-400">({{ $objective->type }})</span>
                            </th>
                        @endforeach
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            Overall Outcome
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @foreach($participants as $participant)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $participant->name }}
                                <br>
                                <span class="text-xs text-gray-500">{{ $participant->cadre?->name }}</span>
                            </td>
                            @foreach($objectives as $objective)
                                @php
                                    $result = $participant->objectiveResults->where('objective_id', $objective->id)->first();
                                @endphp
                                <td class="px-4 py-2 text-center text-sm">
                                    @if($result)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            {{ $result->result === 'skilled' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' }}">
                                            {{ ucfirst($result->result) }}
                                        </span>
                                        @if($result->grade)
                                            <br>
                                            <span class="text-xs text-gray-500">{{ $result->grade->name }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-2 text-center text-sm">
                                @if($participant->outcome)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        {{ strtolower($participant->outcome->name) === 'pass' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' }}">
                                        {{ $participant->outcome->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>