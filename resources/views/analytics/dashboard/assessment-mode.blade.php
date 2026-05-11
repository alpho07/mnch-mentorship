@php
    $totalAssessments       = $summaryStats['totalAssessments'] ?? 0;
    $facilitiesAssessed     = $summaryStats['facilitiesAssessed'] ?? 0;
    $allFacilities          = $summaryStats['allFacilities'] ?? 0;
    $avgScore               = $summaryStats['avgScore'] ?? 0;
    $withSkillsLab          = $summaryStats['withSkillsLab'] ?? 0;
    $eligible               = $summaryStats['eligible'] ?? 0;
    $facilityCoverage       = $summaryStats['facilityCoveragePercent'] ?? 0;
    $yoyChange              = $summaryStats['yoyChange'] ?? 0;
    $avgColor               = $avgScore >= 80 ? 'up' : ($avgScore >= 50 ? 'flat' : 'down');
@endphp

{{-- ████████ ASSESSMENT FILTERS ████████ --}}
<div class="dash-section">
    <div class="collapse show" id="assessmentFilters">
        <div class="filter-card">
            <form method="GET" action="{{ route('analytics.dashboard.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="mode" value="assessment">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Years</option>
                        @foreach($availableYears as $yr)
                            <option value="{{ $yr }}" {{ $selectedYear == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">County</label>
                    <select name="county_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Counties</option>
                        @foreach($counties ?? [] as $county)
                            <option value="{{ $county->id }}" {{ ($selectedCounty ?? '') == $county->id ? 'selected' : '' }}>{{ $county->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Assessment Type</label>
                    <select name="assessment_type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="baseline" {{ ($selectedAssessmentType ?? '') === 'baseline' ? 'selected' : '' }}>Baseline</option>
                        <option value="midline"  {{ ($selectedAssessmentType ?? '') === 'midline'  ? 'selected' : '' }}>Midline</option>
                        <option value="endline"  {{ ($selectedAssessmentType ?? '') === 'endline'  ? 'selected' : '' }}>Endline</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    <a href="{{ route('analytics.dashboard.index', ['mode' => 'assessment']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ████████ KPI STRIP ████████ --}}
<div class="kpi-strip-wrap">
    <div class="kpi-strip">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-clipboard-check"></i></div>
            <div class="kpi-value">{{ number_format($totalAssessments) }}</div>
            <div class="kpi-label">Total Assessments</div>
            @if($yoyChange > 0)
                <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> {{ $yoyChange }}% YoY</span>
            @elseif($yoyChange < 0)
                <span class="kpi-trend down"><i class="fas fa-arrow-down"></i> {{ abs($yoyChange) }}% YoY</span>
            @else
                <span class="kpi-trend flat"><i class="fas fa-minus"></i> Stable</span>
            @endif
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-hospital"></i></div>
            <div class="kpi-value">{{ number_format($facilitiesAssessed) }}</div>
            <div class="kpi-label">Facilities Assessed</div>
            <span class="kpi-trend flat"><i class="fas fa-building"></i> {{ $facilityCoverage }}% coverage</span>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-star-half-alt"></i></div>
            <div class="kpi-value">{{ $avgScore }}%</div>
            <div class="kpi-label">Avg Score</div>
            <span class="kpi-trend {{ $avgColor }}">
                {{ $avgScore >= 80 ? 'Good' : ($avgScore >= 50 ? 'Fair' : 'Needs Work') }}
            </span>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-flask"></i></div>
            <div class="kpi-value">{{ number_format($withSkillsLab) }}</div>
            <div class="kpi-label">Have Skills Lab</div>
            @if($facilitiesAssessed > 0)
                <span class="kpi-trend flat">{{ round(($withSkillsLab / $facilitiesAssessed) * 100) }}% of assessed</span>
            @endif
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-check-double"></i></div>
            <div class="kpi-value">{{ number_format($eligible) }}</div>
            <div class="kpi-label">Eligible for Mentorship</div>
            @if($withSkillsLab > 0)
                @php $partials = $withSkillsLab - $eligible; @endphp
                <span class="kpi-trend flat">{{ $partials }} partial</span>
            @endif
        </div>
    </div>
</div>

{{-- ████████ INSIGHTS ████████ --}}
@if(!empty($insights))
<div class="dash-section">
    <div class="section-title"><i class="fas fa-lightbulb"></i> Insights</div>
    <div class="insights-grid">
        @foreach($insights as $insight)
            <div class="insight-card {{ $insight['type'] }}">
                <div class="insight-icon"><i class="fas fa-{{ $insight['icon'] }}"></i></div>
                <div class="insight-text">{{ $insight['text'] }}</div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- ████████ CHARTS ROW 1 ████████ --}}
<div class="dash-section">
    <div class="chart-row">
        <div class="chart-2-3">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-chart-bar"></i> Assessments Over Time</h6>
                    <small>Last 12 months by type</small>
                </div>
                <div class="chart-card-body"><canvas id="assessmentTrendChart" height="100"></canvas></div>
            </div>
        </div>
        <div class="chart-1-3">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-circle-notch"></i> Grade Distribution</h6>
                    <small>Completed assessments</small>
                </div>
                <div class="chart-card-body" style="display:flex;justify-content:center;align-items:center;">
                    <canvas id="gradeDistChart" style="max-height:220px;max-width:220px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ████████ CHARTS ROW 2 ████████ --}}
<div class="dash-section">
    <div class="chart-row">
        <div class="chart-half">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-layer-group"></i> Section Score Averages</h6>
                    <small>Avg % across all completed assessments</small>
                </div>
                <div class="chart-card-body"><canvas id="sectionScoreChart" height="160"></canvas></div>
            </div>
        </div>
        <div class="chart-half">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-tasks"></i> Assessment Status</h6>
                    <small>Draft / In Progress / Completed</small>
                </div>
                <div class="chart-card-body" style="display:flex;justify-content:center;align-items:center;">
                    <canvas id="statusChart" style="max-height:220px;max-width:220px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ████████ FACILITIES READINESS TABLE ████████ --}}
<div class="dash-section" x-data="{
    filterSkillsLab: 'all',
    filterEligibility: 'all',
    filterFeedback: 'all',
    filterType: 'all',
    matches(row) {
        if (this.filterSkillsLab !== 'all' && row.dataset.skillsLab !== this.filterSkillsLab) return false;
        if (this.filterEligibility !== 'all' && row.dataset.eligibility !== this.filterEligibility) return false;
        if (this.filterFeedback !== 'all' && row.dataset.feedback !== this.filterFeedback) return false;
        if (this.filterType !== 'all' && row.dataset.atype !== this.filterType) return false;
        return true;
    }
}">
    <div class="section-title"><i class="fas fa-table"></i> Facilities Readiness &amp; Mentorship Eligibility</div>

    {{-- Table filters --}}
    <div class="filter-card mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">Skills Lab</label>
                <select class="form-select form-select-sm" x-model="filterSkillsLab">
                    <option value="all">All</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Eligibility</label>
                <select class="form-select form-select-sm" x-model="filterEligibility">
                    <option value="all">All</option>
                    <option value="eligible">Eligible</option>
                    <option value="partial">Partial</option>
                    <option value="not_eligible">Not Eligible</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Feedback</label>
                <select class="form-select form-select-sm" x-model="filterFeedback">
                    <option value="all">All</option>
                    <option value="given">Given</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Type</label>
                <select class="form-select form-select-sm" x-model="filterType">
                    <option value="all">All Types</option>
                    <option value="baseline">Baseline</option>
                    <option value="midline">Midline</option>
                    <option value="endline">Endline</option>
                </select>
            </div>
        </div>
    </div>

    <div class="chart-card" style="overflow-x:auto;">
        <table class="analytics-table" id="readinessTable">
            <thead>
                <tr>
                    <th>Facility</th>
                    <th>Subcounty / County</th>
                    <th>Level</th>
                    <th>Latest Assessment</th>
                    <th>Assessed By</th>
                    <th>Skills Lab</th>
                    <th>Feedback</th>
                    <th>Eligibility</th>
                    <th>Mentorships</th>
                </tr>
            </thead>
            <tbody>
                @forelse($facilitiesReadiness as $assessment)
                    @php
                        $slKey   = $assessment->has_skills_lab ? 'yes' : 'no';
                        $fbKey   = $assessment->feedback_given ? 'given' : 'pending';
                        $eligKey = $assessment->eligibility_status;
                    @endphp
                    <tr
                        x-show="matches($el)"
                        data-skills-lab="{{ $slKey }}"
                        data-eligibility="{{ $eligKey }}"
                        data-feedback="{{ $fbKey }}"
                        data-atype="{{ $assessment->assessment_type }}"
                    >
                        <td>
                            <div class="fw-semibold" style="color:var(--gray-800)">{{ $assessment->facility->name }}</div>
                            <small class="badge bg-secondary">{{ $assessment->facility->mfl_code }}</small>
                        </td>
                        <td>
                            <div>{{ $assessment->facility->subcounty->name ?? '—' }}</div>
                            <small style="color:var(--gray-500)">{{ $assessment->facility->subcounty->county->name ?? '—' }}</small>
                        </td>
                        <td>
                            @if($assessment->facility->facilityLevel)
                                <span class="badge" style="background:var(--teal-50);color:var(--teal-dark)">{{ $assessment->facility->facilityLevel->name }}</span>
                            @else
                                <span style="color:var(--gray-500)">—</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $assessment->assessment_date->format('d M Y') }}</div>
                            <span class="badge" style="background:{{ $assessment->assessment_type === 'baseline' ? 'rgba(0,151,167,.12)' : ($assessment->assessment_type === 'midline' ? 'rgba(245,158,11,.12)' : 'rgba(139,92,246,.12)') }};color:{{ $assessment->assessment_type === 'baseline' ? 'var(--teal-dark)' : ($assessment->assessment_type === 'midline' ? '#92400E' : '#5B21B6') }}">
                                {{ ucfirst($assessment->assessment_type) }}
                            </span>
                            @if($assessment->status === 'completed')
                                <div style="margin-top:.35rem;">
                                    <a href="/admin/assessments/{{ $assessment->id }}/summary"
                                       target="_blank"
                                       style="font-size:.72rem;font-weight:600;color:var(--teal);text-decoration:none;display:inline-flex;align-items:center;gap:.25rem;padding:.15rem .45rem;border-radius:6px;background:var(--teal-50);border:1px solid var(--teal-100);"
                                       title="View Assessment Summary">
                                        <i class="fas fa-file-alt"></i> View Report
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td>{{ $assessment->assessor_name }}</td>
                        <td>
                            @if($assessment->has_skills_lab)
                                <span class="badge" style="background:#D1FAE5;color:#065F46"><i class="fas fa-check me-1"></i>Yes</span>
                            @else
                                <span class="badge" style="background:#FEE2E2;color:#991B1B"><i class="fas fa-times me-1"></i>No</span>
                            @endif
                        </td>
                        <td>
                            @if($assessment->feedback_given)
                                <span style="font-size:.8rem;color:#065F46">
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    {{ $assessment->feedbackGivenBy->name ?? 'System' }}
                                    @if($assessment->feedback_given_at)
                                        <br><small style="color:var(--gray-500)">{{ $assessment->feedback_given_at->format('d M Y') }}</small>
                                    @endif
                                </span>
                            @else
                                <span class="badge" style="background:#FEF3C7;color:#92400E"><i class="fas fa-clock me-1"></i>Pending</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $eligStyle = match($assessment->eligibility_status) {
                                    'eligible'     => 'background:#D1FAE5;color:#065F46',
                                    'partial'      => 'background:#FEF3C7;color:#92400E',
                                    default        => 'background:#FEE2E2;color:#991B1B',
                                };
                                $eligLabel = match($assessment->eligibility_status) {
                                    'eligible' => '🟢 Eligible',
                                    'partial'  => '🟡 Partial',
                                    default    => '🔴 Not Eligible',
                                };
                            @endphp
                            <span class="badge" style="{{ $eligStyle }}">{{ $eligLabel }}</span>
                        </td>
                        <td>
                            @if($assessment->mentorship_count > 0)
                                <a href="{{ route('analytics.dashboard.facility.mentorship-breakdown', ['facility' => $assessment->facility_id, 'year' => $selectedYear]) }}"
                                   style="color:var(--teal);font-weight:700;text-decoration:none;">
                                    {{ $assessment->mentorship_count }}
                                    <i class="fas fa-external-link-alt ms-1" style="font-size:.7rem"></i>
                                </a>
                            @else
                                <span style="color:var(--gray-500)">0</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:2rem;color:var(--gray-500)">
                            <i class="fas fa-clipboard-list fa-2x mb-2 d-block"></i>
                            No assessed facilities found for the current filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ████████ CHART JS ████████ --}}
@push('scripts')
<script>
(function () {
    const trendChart = document.getElementById('assessmentTrendChart');
    if (trendChart) {
        new Chart(trendChart, {
            type: 'bar',
            data: {
                labels: {!! json_encode(array_column($chartData['monthlyTrend'], 'short')) !!},
                datasets: [
                    { label: 'Baseline', data: {!! json_encode(array_column($chartData['monthlyTrend'], 'baseline')) !!}, backgroundColor: '#0097A7' },
                    { label: 'Midline',  data: {!! json_encode(array_column($chartData['monthlyTrend'], 'midline'))  !!}, backgroundColor: '#F59E0B' },
                    { label: 'Endline',  data: {!! json_encode(array_column($chartData['monthlyTrend'], 'endline'))  !!}, backgroundColor: '#8B5CF6' },
                ]
            },
            options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { x: { stacked: false }, y: { beginAtZero: true, stacked: false } } }
        });
    }

    const gradeChart = document.getElementById('gradeDistChart');
    if (gradeChart) {
        new Chart(gradeChart, {
            type: 'doughnut',
            data: {
                labels: ['Good (≥80%)', 'Fair (50–79%)', 'Poor (<50%)'],
                datasets: [{ data: [{{ $chartData['gradeDistribution']['green'] }}, {{ $chartData['gradeDistribution']['yellow'] }}, {{ $chartData['gradeDistribution']['red'] }}], backgroundColor: ['#10B981','#F59E0B','#EF4444'], borderWidth: 2 }]
            },
            options: { responsive: true, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
        });
    }

    const sectionChart = document.getElementById('sectionScoreChart');
    if (sectionChart) {
        new Chart(sectionChart, {
            type: 'bar',
            data: {
                labels: {!! json_encode($chartData['sectionAverages']->pluck('name')->toArray()) !!},
                datasets: [{
                    label: 'Avg Score (%)',
                    data: {!! json_encode($chartData['sectionAverages']->pluck('percentage')->toArray()) !!},
                    backgroundColor: {!! json_encode($chartData['sectionAverages']->pluck('color')->toArray()) !!},
                }]
            },
            options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 100 } } }
        });
    }

    const statusChart = document.getElementById('statusChart');
    if (statusChart) {
        new Chart(statusChart, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress', 'Draft'],
                datasets: [{ data: [{{ $chartData['statusBreakdown']['completed'] }}, {{ $chartData['statusBreakdown']['in_progress'] }}, {{ $chartData['statusBreakdown']['draft'] }}], backgroundColor: ['#0097A7','#F59E0B','#94A3B8'], borderWidth: 2 }]
            },
            options: { responsive: true, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
        });
    }
})();
</script>
@endpush
