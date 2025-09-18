@extends('layouts.dashboard')

@section('title', $program->title . ' - Program Analytics')

@section('content')
<div class="container-fluid px-4">
    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="h2 mb-1">{{ $program->title }}</h1>
                <p class="mb-2">{{ $county->name }} County | {{ $mode === 'training' ? 'Training Program' : 'Mentorship Program' }}</p>
                <div class="d-flex gap-3 flex-wrap">
                    @if($program->identifier)
                    <span class="badge bg-white text-dark">ID: {{ $program->identifier }}</span>
                    @endif
                    @if($program->start_date)
                    <span class="badge bg-white text-dark">Year: {{ \Carbon\Carbon::parse($program->start_date)->format('Y') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Program Statistics -->
    <div class="stats-grid">
        <div class="stats-card">
            <div class="icon"><i class="fas fa-hospital"></i></div>
            <h3>{{ number_format($facilities->count() ?? 0) }}</h3>
            <p>Facilitie(s)</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="icon"><i class="fas fa-users"></i></div>
            <h3>{{ number_format($programStats['totalParticipants'] ?? 0) }}</h3>
            <p>Total {{ $mode === 'training' ? 'Participants' : 'Mentees' }}</p>
        </div>
        <!--div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <h3>{{ number_format($programStats['completedParticipants'] ?? 0) }}</h3>
            <p>Completed</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <div class="icon"><i class="fas fa-chart-line"></i></div>
            <h3>{{ $programStats['completionPercentage'] ?? 0 }}%</h3>
            <p>Completion Rate</p>
        </div-->
    </div>

  

    <!-- Facilities List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Participating Facilities</h5>
                    <small class="text-muted">Click on a facility to view detailed participant information</small>
                </div>
                <div class="card-body">
                    @if($facilities->count() > 0)
                        <div class="row g-3">
                            @foreach($facilities as $facility)
                            <div class="col-lg-4 col-md-6">
                                <div class="facility-card card h-100" data-facility-id="{{ $facility->id }}">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="card-title mb-1">{{ $facility->name }}</h6>
                                                <small class="text-muted">{{ $facility->subcounty->name ?? 'N/A' }}</small>
                                            </div>
                                            <span class="badge bg-info">{{ $facility->facilityType->name ?? 'N/A' }}</span>
                                        </div>
                                        
                                        @if($facility->mfl_code)
                                        <div class="mb-2">
                                            <small class="text-muted d-block">MFL Code</small>
                                            <span class="fw-semibold">{{ $facility->mfl_code }}</span>
                                        </div>
                                        @endif
                                        
<!--                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <small class="text-muted d-block">{{ $mode === 'training' ? 'Participants' : 'Mentees' }}</small>
                                                <span class="fw-semibold fs-5 text-primary">{{ number_format($facility->participants_count ?? 0) }}</span>
                                            </div>
                                          
                                        </div>-->
                                      
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Click to view details</small>
                                            <i class="fas fa-arrow-right text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state">
                            <div class="icon"><i class="fas fa-hospital"></i></div>
                            <h6>No Participating Facilities</h6>
                            <p>No facilities are participating in this {{ $mode }} program.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('custom-styles')
.facility-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    overflow: hidden;
    height: 100%;
}

.facility-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.15);
    border-color: #3b82f6;
}

.facility-card .card-body {
    padding: 1.5rem;
    position: relative;
}

.facility-card .card-body::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(29, 78, 216, 0.1) 100%);
    border-radius: 50%;
    transform: translate(20px, -20px);
}

.alert {
    border: none;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
    .facility-card .card-body {
        padding: 1rem;
    }
}
@endsection

@section('page-scripts')
// Facility cards click
document.querySelectorAll('.facility-card').forEach(card => {
    card.addEventListener('click', function() {
        const facilityId = this.dataset.facilityId;
        if (facilityId) {
            navigateToFacility(facilityId);
        }
    });
});

function navigateToFacility(facilityId) {
    const params = new URLSearchParams({
        mode: '{{ $mode ?? "training" }}'
    });
    
    const currentYear = '{{ $selectedYear ?? "" }}';
    if (currentYear) {
        params.set('year', currentYear);
    }
    
    window.location.href = `/analytics/dashboard/county/{{ $county->id }}/program/{{ $program->id }}/facility/${facilityId}?${params.toString()}`;
}
@endsection