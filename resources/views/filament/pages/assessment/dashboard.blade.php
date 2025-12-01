<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Assessment summary infolist --}}
        {{ $this->getInfolist('assessment_summary') }}

        {{-- Sections grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">

            @foreach ($sections as $section)
                <div class="p-4 rounded-xl border shadow-sm flex items-center justify-between bg-white">

                    <div>
                        <h3 class="text-lg font-semibold">{{ $section['label'] }}</h3>

                        <p class="text-sm text-gray-600 mt-1">
                        Status:
                            @if($section['done'])
                                <span class="text-green-600 font-semibold">Completed</span>
                            @else
                                <span class="text-red-600 font-semibold">Pending</span>
                            @endif
                        </p>
                    </div>

                    @if ($section['route'])
                        <a href="{{ $section['route'] }}"
                   class="px-4 py-2 rounded-lg bg-primary-600 text-white hover:bg-primary-700 transition">
                            {{ $section['done'] ? 'View' : 'Continue' }}
                        </a>
                    @else
                        <span class="text-gray-400 italic">Done</span>
                    @endif

                </div>
            @endforeach
        </div>

    </div>
</x-filament-panels::page>
