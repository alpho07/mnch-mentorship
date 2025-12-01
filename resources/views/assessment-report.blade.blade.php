<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Assessment Report - {{ $assessment->assessment_number }}</title>
        @vite('resources/css/app.css')
        <style>
            @media print {
                .no-print {
                    display: none !important;
                }
                .page-break {
                    page-break-after: always;
                }
            }
        </style>
    </head>
    <body class="bg-gray-50">
    <div class="max-w-7xl mx-auto p-8">
            <!-- Header with Actions -->
        <div class="flex justify-between items-center mb-8 no-print">
            <a href="{{ url()->previous() }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                    Back to Assessment
            </a>
            <div class="flex gap-4">
                <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Print Report
                </button>
                <a href="{{ route('assessment.download', $assessment) }}" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Download PDF
                </a>
            </div>
        </div>

            <!-- Report Content -->
        <div class="bg-white rounded-xl shadow-lg p-8">
                <!-- Title and Header -->
            <div class="border-b-4 border-blue-600 pb-6 mb-8">
                <h1 class="text-4xl font-bold text-gray-900 mb-2">
                    {{ $assessment->assessmentType->name }}
                </h1>
                <p class="text-xl text-gray-600">
                        Assessment Report
                </p>
                <p class="text-lg text-gray-500 mt-2">
                    {{ $assessment->assessment_number }} | {{ $assessment->assessment_date->format('d F Y') }}
                </p>
            </div>

                <!-- Facility Information -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 border-l-4 border-blue-600 pl-4">
                        Facility Information
                </h2>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Facility Name</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $assessment->facility->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">MFL Code</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $assessment->facility->mfl_code ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Facility Level</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $assessment->facility->facilityLevel->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Facility Type</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $assessment->facility->facilityType->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">County</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $assessment->facility->county }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Sub-County</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $assessment->facility->subcounty->name }}</p>
                    </div>
                </div>
            </div>

                <!-- Scoring Summary -->
            @if($assessment->status === 'completed')
                <div class="mb-8 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Overall Performance</h2>
                    <div class="grid grid-cols-4 gap-6">
                        <div class="text-center">
                            <p class="text-sm text-gray-600 mb-2">Total Score</p>
                            <p class="text-3xl font-bold text-blue-600">
                                {{ number_format($assessment->total_score, 1) }}/{{ number_format($assessment->max_score, 1) }}
                            </p>
                        </div>
                        <div class="text-center">
                            <p class="text-sm text-gray-600 mb-2">Percentage</p>
                            <p class="text-3xl font-bold text-blue-600">{{ number_format($assessment->percentage, 1) }}%</p>
                        </div>
                        <div class="text-center">
                            <p class="text-sm text-gray-600 mb-2">Grade</p>
                            <p class="text-3xl font-bold 
                            @if($assessment->grade === 'Excellent') text-green-600
                            @elseif($assessment->grade === 'Good') text-blue-600
                            @elseif($assessment->grade === 'Satisfactory') text-yellow-600
                            @else text-red-600
                            @endif
                               ">
                                {{ $assessment->grade }}
                            </p>
                        </div>
                        <div class="text-center">
                            <p class="text-sm text-gray-600 mb-2">Status</p>
                            <p class="text-lg font-semibold text-green-600">{{ ucfirst($assessment->status) }}</p>
                        </div>
                    </div>
                </div>
            @endif

                <!-- Section Scores -->
            @if(!empty($sectionScores))
                <div class="mb-8 page-break">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 border-l-4 border-blue-600 pl-4">
                        Section-wise Performance
                    </h2>
                    <div class="space-y-4">
                        @foreach($sectionScores as $sectionName => $scores)
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $sectionName }}</h3>
                                    <span class="text-xl font-bold 
                                @if($scores['percentage'] >= 90) text-green-600
                                @elseif($scores['percentage'] >= 75) text-blue-600
                                @elseif($scores['percentage'] >= 60) text-yellow-600
                                @else text-red-600
                                @endif
                                      ">
                                        {{ number_format($scores['percentage'], 1) }}%
                                    </span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="flex-1 bg-gray-200 rounded-full h-3 overflow-hidden">
                                        <div class="h-full rounded-full transition-all
                                    @if($scores['percentage'] >= 90) bg-green-500
                                    @elseif($scores['percentage'] >= 75) bg-blue-500
                                    @elseif($scores['percentage'] >= 60) bg-yellow-500
                                    @else bg-red-500
                                    @endif
                                        " style="width: {{ $scores['percentage'] }}%"></div>
                                    </div>
                                    <span class="text-sm text-gray-600">
                                        {{ number_format($scores['score'], 1) }}/{{ number_format($scores['max_score'], 1) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

                <!-- Detailed Responses -->
            <div class="page-break">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 border-l-4 border-blue-600 pl-4">
                        Detailed Assessment Responses
                </h2>

                @foreach($reportData as $sectionData)
                    <div class="mb-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 bg-gray-100 p-3 rounded-lg">
                            {{ $sectionData['section_name'] }}
                        </h3>

                        <div class="space-y-6">
                            @foreach($sectionData['questions'] as $questionData)
                                <div class="border-l-2 border-gray-300 pl-4 py-2">
                                    <p class="font-semibold text-gray-900 mb-2">
                                        <span class="text-blue-600">{{ $questionData['question_code'] }}:</span>
                                        {{ $questionData['question_text'] }}
                                    </p>

                                    @if(!empty($questionData['responses']))
                                @foreach($questionData['responses'] as $response)
                                            <div class="ml-4 mb-2 p-3 bg-gray-50 rounded">
                                                @if($response['location'])
                                                    <p class="text-sm font-semibold text-gray-700 mb-1">
                                                        Location: <span class="text-blue-600">{{ $response['location'] }}</span>
                                                    </p>
                                                @endif

                                                <p class="text-gray-800">
                                        Response: 
                                                    <span class="font-semibold 
                                            @if($response['value'] === 'Yes') text-green-600
                                            @elseif($response['value'] === 'No') text-red-600
                                            @elseif($response['value'] === 'Partially') text-yellow-600
                                            @else text-gray-600
                                            @endif
                                              ">
                                                        {{ $response['value'] }}
                                                    </span>

                                                    @if($response['score'] !== null)
                                                        <span class="ml-2 text-sm text-gray-600">
                                                            (Score: {{ $response['score'] }})
                                                        </span>
                                                    @endif
                                                </p>

                                                @if($response['explanation'])
                                                    <p class="text-sm text-gray-600 mt-2 italic">
                                                        <strong>Explanation:</strong> {{ $response['explanation'] }}
                                                    </p>
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <p class="ml-4 text-gray-500 italic">No response recorded</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

                <!-- Observations and Recommendations -->
            @if($assessment->observations || $assessment->recommendations)
                <div class="mt-8 page-break">
                    @if($assessment->observations)
                        <div class="mb-6">
                            <h2 class="text-2xl font-bold text-gray-900 mb-4 border-l-4 border-blue-600 pl-4">
                            Key Observations
                            </h2>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                                <p class="text-gray-800 whitespace-pre-line">{{ $assessment->observations }}</p>
                            </div>
                        </div>
                    @endif

                    @if($assessment->recommendations)
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 mb-4 border-l-4 border-green-600 pl-4">
                            Recommendations
                            </h2>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                                <p class="text-gray-800 whitespace-pre-line">{{ $assessment->recommendations }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

                <!-- Footer -->
            <div class="mt-12 pt-6 border-t border-gray-300 text-center text-gray-600">
                <p class="mb-2">
                    <strong>Assessor:</strong> {{ $assessment->assessor->name ?? $assessment->assessor_name ?? 'N/A' }}
                    @if($assessment->assessor_designation)
                        ({{ $assessment->assessor_designation }})
                    @endif
                </p>
                <p class="text-sm">
                    Report Generated: {{ now()->format('d F Y H:i:s') }}
                </p>
            </div>
        </div>
    </div>
</body>
</html>