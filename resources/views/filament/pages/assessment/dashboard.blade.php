<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Assessment Summary --}}
        {{ $this->getInfolist('assessment_summary') }}
        
        {{-- Section Completion Tracker --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6 mt-6">
            @foreach ($sections as $section)
                <div class="p-5 rounded-xl border bg-white text-center shadow-sm hover:shadow-md transition">
                    {{-- Circular status badge --}}
                    <div class="mx-auto mb-3">
                        @if($section['done'])
                            <div class="w-16 h-16 rounded-full bg-green-100 text-green-700 
                                flex items-center justify-center text-2xl font-bold shadow-sm">
                                ✓
                            </div>
                        @else
                            <div class="w-16 h-16 rounded-full bg-red-100 text-red-700 
                                flex items-center justify-center text-2xl font-bold shadow-sm">
                                !
                            </div>
                        @endif
                    </div>
                    
                    {{-- Label --}}
                    <h3 class="text-sm font-semibold">{{ $section['label'] }}</h3>
                    
                    {{-- Action --}}
                    <div class="mt-4">
                        @if ($section['route'])
                            @if($section['done'])
                                {{-- Green button for completed sections --}}
                                <a href="{{ $section['route'] }}"
                                   style="background-color: #16a34a !important;"
                                   class="px-3 py-2 rounded-lg text-white text-sm block mx-auto w-24 transition hover:opacity-90">
                                    View
                                </a>
                            @else
                                {{-- Blue button for incomplete sections --}}
                                <a href="{{ $section['route'] }}"
                                   class="px-3 py-2 rounded-lg bg-primary-600 text-white hover:bg-primary-700 text-sm block mx-auto w-24 transition">
                                    Continue
                                </a>
                            @endif
                        @else
                            <span class="text-gray-400 text-sm italic">Done</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>