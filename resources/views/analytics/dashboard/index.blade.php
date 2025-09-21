@extends('layouts.dashboard')

@section('title', 'Training Analytics Dashboard')

@section('content')

    <style>
    /* Healthcare Training Dashboard - Clean CSS with Mentorship Layout Support */

    /* CSS Variables */
    :root {
        --primary-color: #2563eb;
        --success-color: #059669;
        --warning-color: #d97706;
        --danger-color: #dc2626;
        --info-color: #0891b2;
        --orange-color: #f97316;

        --gray-50: #f8fafc;
        --gray-100: #f1f5f9;
        --gray-200: #e2e8f0;
        --gray-300: #cbd5e1;
        --gray-400: #94a3b8;
        --gray-500: #64748b;
        --gray-600: #475569;
        --gray-700: #334155;
        --gray-800: #1e293b;
        --gray-900: #0f172a;
    }

    /* Layout Classes for Mentorship Mode */
    .layout-training {
        display: flex;
        flex-direction: column;
    }

    .layout-mentorship {
        display: flex;
        gap: 1.5rem;
        height: calc(100vh - 200px);
    }

    .layout-mentorship .map-section {
        flex: 2;
        min-width: 60%;
    }

    .layout-mentorship .sidebar-section {
        flex: 1;
        min-width: 35%;
        max-height: 100%;
        overflow-y: auto;
    }

    /* County Sidebar for Mentorship Mode */
    .county-sidebar {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border: 1px solid var(--gray-200);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        background: var(--gray-50);
        border-radius: 12px 12px 0 0;
    }

    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }

    .county-summary-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .summary-card {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
    }

    .summary-card h4 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        color: var(--primary-color);
    }

    .summary-card p {
        margin: 0;
        font-size: 0.875rem;
        color: var(--gray-600);
    }

    .facility-item {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .facility-item:hover {
        border-color: var(--success-color);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .facility-item h6 {
        margin-bottom: 0.5rem;
        color: var(--gray-800);
    }

    .facility-item .facility-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .facility-item .meta-item {
        text-align: center;
        padding: 0.25rem;
        background: var(--gray-50);
        border-radius: 4px;
    }

    .facility-item .meta-value {
        font-weight: 700;
        color: var(--success-color);
    }

    .facility-item .meta-label {
        font-size: 0.75rem;
        color: var(--gray-500);
    }

    .sidebar-empty {
        text-align: center;
        padding: 2rem;
        color: var(--gray-500);
    }

    /* Base Dashboard Styles */
    .page-header {
        margin-bottom: 1.5rem;
    }

    .page-header h1 {
        color: var(--gray-800);
        font-weight: 700;
    }

    .page-header p {
        color: var(--gray-500);
    }

    /* Filter Card */
    .filter-card {
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-200) 100%);
    }

    .filter-card .form-label {
        font-weight: 600;
        color: var(--gray-700);
        margin-bottom: 0.5rem;
    }

    .filter-card .form-select,
    .filter-card .form-control {
        border-radius: 8px;
        border: 1px solid var(--gray-300);
        transition: all 0.2s ease;
    }

    .filter-card .form-select:focus,
    .filter-card .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
    }

    /* Mode Toggle */
    .mode-toggle {
        display: flex;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        background: white;
    }

    .mode-btn {
        padding: 0.75rem 1.5rem;
        border: none;
        background: white;
        color: var(--gray-500);
        transition: all 0.2s ease;
        font-weight: 600;
        cursor: pointer;
    }

    .mode-btn.active {
        background: var(--primary-color);
        color: white;
    }

    .mode-btn:hover:not(.active) {
        background: var(--gray-100);
        color: var(--primary-color);
    }

    /* Statistics Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stats-card {
        background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        transition: transform 0.2s ease;
    }

    .stats-card:hover {
        transform: translateY(-2px);
    }

    .stats-card .icon {
        font-size: 2rem;
        opacity: 0.9;
        flex-shrink: 0;
    }

    .stats-card h3 {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
    }

    .stats-card p {
        margin: 0;
        opacity: 0.9;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }

    /* Training Card Styles */
    .training-card {
        border: 2px solid var(--gray-200);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        cursor: pointer;
        transition: all 0.3s ease;
        background: white;
        position: relative;
        overflow: hidden;
    }

    .training-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.1), transparent);
        transition: left 0.5s;
    }

    .training-card:hover::before {
        left: 100%;
    }

    .training-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(59, 130, 246, 0.15);
        border-color: var(--primary-color);
    }

    .training-card.selected {
        border-color: var(--primary-color);
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.25);
        transform: translateY(-2px);
    }

    .training-card.selected::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(135deg, var(--primary-color) 0%, #1d4ed8 100%);
        border-radius: 0 4px 4px 0;
    }

    .training-card .training-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--gray-800);
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }

    .training-card.selected .training-title {
        color: #1d4ed8;
    }

    .training-card .training-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .training-card .meta-item {
        text-align: center;
        padding: 0.5rem;
        background: rgba(248, 250, 252, 0.8);
        border-radius: 8px;
    }

    .training-card.selected .meta-item {
        background: rgba(59, 130, 246, 0.1);
    }

    .training-card .meta-label {
        display: block;
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-bottom: 0.25rem;
        font-weight: 500;
    }

    .training-card .meta-value {
        display: block;
        font-size: 1rem;
        font-weight: 700;
        color: var(--gray-900);
    }

    .training-card.selected .meta-value {
        color: #1d4ed8;
    }

    /* Map Container with Legend and Overlay */
    .map-container {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
    }

    #kenyaMap {
        border-radius: 8px;
        border: 1px solid var(--gray-200);
    }

    /* Map Legend */
    .map-legend {
        position: absolute;
        bottom: 20px;
        right: 20px;
        background: white;
        padding: 12px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border: 1px solid var(--gray-200);
        z-index: 1000;
        min-width: 160px;
    }

    .map-legend h6 {
        margin: 0 0 8px 0;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--gray-700);
    }

    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 4px;
        font-size: 0.75rem;
    }

    .legend-item:last-child {
        margin-bottom: 0;
    }

    .legend-color {
        width: 16px;
        height: 16px;
        border-radius: 3px;
        margin-right: 8px;
        border: 1px solid rgba(0,0,0,0.1);
    }

    .legend-label {
        color: var(--gray-600);
        font-weight: 500;
    }

    /* Map Overlay Card */
    .map-overlay {
        position: absolute;
        top: 20px;
        left: 20px;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%);
        backdrop-filter: blur(10px);
        padding: 16px;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.8);
        z-index: 1000;
        min-width: 220px;
        max-width: 300px;
    }

    .overlay-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e2e8f0;
    }

    .overlay-header i {
        font-size: 1.2rem;
    }

    .overlay-header h6 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
    }

    .coverage-section {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s ease;
    }

    .coverage-section:hover {
        background: rgba(59, 130, 246, 0.03);
        border-radius: 8px;
        margin: 0 -8px;
        padding: 12px 8px;
    }

    .coverage-section:last-child {
        border-bottom: none;
    }

    .overall-section {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border-radius: 10px;
        padding: 16px 12px;
        margin: 8px -4px 0 -4px;
        border: 1px solid #bae6fd;
    }

    .coverage-icon {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .coverage-icon i {
        font-size: 1rem;
    }

    .coverage-content {
        flex: 1;
        min-width: 0;
    }

    .coverage-label {
        font-size: 0.9rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 4px;
        line-height: 1.2;
    }

    .coverage-fraction {
        font-size: 0.8rem;
        color: #6b7280;
        margin-bottom: 2px;
        font-weight: 500;
    }

    .coverage-fraction span {
        font-weight: 700;
        color: #374151;
    }

    .coverage-percentage {
        font-size: 0.85rem;
        font-weight: 700;
        color: #2563eb;
        line-height: 1.2;
    }

    .coverage-percentage-large {
        font-size: 1.1rem;
        font-weight: 800;
        color: #059669;
        line-height: 1.2;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    /* Loading Overlay */
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.95);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        border-radius: 8px;
        backdrop-filter: blur(2px);
    }

    .loading-overlay.show {
        display: flex;
    }

    .loading-overlay .spinner-border {
        width: 3rem;
        height: 3rem;
    }

    /* Chart Styles with Better Sizing */
    .chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .chart-container {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--gray-200);
        transition: all 0.2s ease;
        position: relative;
    }

    .chart-container:hover {
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        transform: translateY(-2px);
    }

    /* Department and Cadre Chart Containers */
    .department-chart-container,
    .cadre-chart-container {
        height: 350px;
    }

    .department-chart-container canvas,
    .cadre-chart-container canvas {
        max-height: 280px !important;
        width: 100% !important;
    }

    .facility-chart-container {
        height: 350px;
    }

    .facility-chart-container canvas {
        max-height: 280px !important;
        width: 100% !important;
    }

    /* Chart Headers */
    .chart-container h6 {
        margin-bottom: 1rem;
        font-size: 1rem;
        font-weight: 600;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chart-container h6 i {
        font-size: 1.1rem;
    }

    .chart-container h6 small {
        font-weight: 400;
        font-size: 0.8rem;
    }

    /* Tour Button Styling */
    #startTourBtn {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
        border: none;
        color: white;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    #startTourBtn:hover {
        background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(8, 145, 178, 0.3);
    }

    #startTourBtn::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }

    #startTourBtn:hover::after {
        left: 100%;
    }

    /* Tour Custom Styling */
    .tour-welcome {
        text-align: center;
        padding: 15px;
    }

    .tour-welcome h4 {
        color: #2563eb;
        margin-bottom: 15px;
        font-size: 1.4rem;
    }

    .tour-welcome p {
        margin-bottom: 10px;
        line-height: 1.5;
    }

    .introjs-tooltip {
        max-width: 400px;
        font-size: 14px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        border-radius: 12px;
        border: none;
    }

    .introjs-button {
        background-color: #2563eb;
        border: none;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        margin: 0 5px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .introjs-button:hover {
        background-color: #1d4ed8;
        transform: translateY(-1px);
    }

    .introjs-skipbutton {
        background-color: #6b7280;
    }

    .introjs-skipbutton:hover {
        background-color: #4b5563;
    }

    .introjs-progressbar {
        background-color: #e5e7eb;
        height: 8px;
        border-radius: 4px;
    }

    .introjs-progress {
        background-color: #2563eb;
        border-radius: 4px;
    }

    .introjs-helperLayer {
        border-radius: 8px;
    }

    /* Responsive Design */
    @media (max-width: 1400px) {
        .chart-grid {
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        }
    }

    @media (max-width: 1200px) {
        .layout-mentorship {
            flex-direction: column;
            height: auto;
        }

        .layout-mentorship .map-section,
        .layout-mentorship .sidebar-section {
            min-width: 100%;
        }

        .layout-mentorship .map-section {
            height: 500px;
        }

        .chart-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .county-summary-cards {
            grid-template-columns: 1fr;
        }

        .facility-item .facility-meta {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .map-overlay {
            position: relative;
            top: 0;
            left: 0;
            margin-bottom: 1rem;
            max-width: 100%;
        }

        .map-legend {
            position: relative;
            bottom: 0;
            right: 0;
            margin-top: 1rem;
        }

        .coverage-section {
            gap: 10px;
        }

        .coverage-icon {
            width: 28px;
            height: 28px;
        }

        .coverage-label {
            font-size: 0.85rem;
        }

        .coverage-fraction {
            font-size: 0.75rem;
        }

        .coverage-percentage {
            font-size: 0.8rem;
        }

        .coverage-percentage-large {
            font-size: 1rem;
        }
    }

    /* Animation for value updates */
    .coverage-percentage span,
    .coverage-percentage-large span,
    .coverage-fraction span {
        transition: all 0.3s ease;
    }

    .coverage-percentage span.updating,
    .coverage-percentage-large span.updating,
    .coverage-fraction span.updating {
        transform: scale(1.05);
        color: #059669;
    }
    </style>

<!-- Add Intro.js for guided tour -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intro.js/7.2.0/introjs.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/intro.js/7.2.0/intro.min.js"></script>

<!-- Meta tags for JavaScript -->
    <meta name="dashboard-mode" content="{{ $mode ?? 'training' }}">
    <meta name="dashboard-year" content="{{ $selectedYear ?? '' }}">

    <div class="container-fluid px-4">
    <!-- Header Section -->
         <div class="page-header mt-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2 mb-1" style="color:white">{{ucfirst($mode)}} Analytics Dashboard</h1>
                    <p class="mb-0" style="color:white;">Comprehensive insights across {{$mode}} programs</p>
                </div>

            <!-- Controls -->
                <div class="d-flex gap-3 align-items-center flex-wrap">
                    <!-- Tour Button -->
                    <button class="btn btn-outline-info" id="startTourBtn" title="Take a guided tour of the dashboard">
                        <i class="fas fa-question-circle me-1"></i>
                        Take Tour
                    </button>

                <!-- Filters Toggle -->
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                        <i class="fas fa-filter me-1"></i>Filters
                        <i class="fas fa-chevron-down ms-1"></i>
                    </button>

                <!-- Mode Toggle -->
                    <div class="mode-toggle" style="z-index: 1000 !important;" 
                         data-intro="Switch between Training Programs and Mentorship Programs using these toggles. Training programs are facility-based learning sessions for groups, while mentorships are one-on-one guidance programs between mentors and mentees."
                         data-step="2">
                        <button type="button" class="mode-btn {{ $mode === 'training' ? 'active' : '' }}" data-mode="training">
                        Trainings
                        </button>
                        <button type="button" class="mode-btn {{ $mode === 'mentorship' ? 'active' : '' }}" data-mode="mentorship">
                        Mentorships
                        </button>
                        <a href="{{url('/admin')}}" class="mode-btn" data-mode="admin">
                        Admin
                        </a>
                    </div>
                </div>
            </div>
        </div>

    <!-- Collapsible Filters Section -->
        <div class="collapse mb-4" id="filtersCollapse">
            <div class="card filter-card"
                 data-intro="Use these filters to narrow down your analysis. You can filter by specific programs, years, or search for program names. The filters work together and update the map and charts automatically when changed."
                 data-step="3">
                <div class="card-body">
                    <div class="row g-3 justify-content-center">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">Program</label>
                            <select class="form-select" id="program-filter">
                                <option value="">All Programs</option>
                                @foreach($trainingsList as $training)
                                    <option value="{{ $training->id }}">{{ $training->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">Year</label>
                            <select class="form-select" id="year-filter">
                                <option value="" {{ empty($selectedYear) ? 'selected' : '' }}>All Years</option>
                                @foreach($availableYears ?? [] as $year)
                                    <option value="{{ $year }}" {{ ($selectedYear ?? '') == $year ? 'selected' : '' }}>{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">Search Programs</label>
                            <input type="text" class="form-control" id="search-filter" placeholder="Search training names...">
                        </div>
                        <div class="col-lg-2 col-md-6 d-flex align-items-end">
                            <button class="btn btn-outline-secondary w-100" id="clear-filters">
                                <i class="fas fa-times me-1"></i>Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Summary Statistics -->
        <div class="stats-grid"
             data-intro="These cards show key metrics for your selected mode and filters. Total programs shows the number of active programs, participants shows enrolled users, facilities shows involved healthcare facilities, and coverage shows the percentage of facilities participating."
             data-step="4">
            <div class="stats-card">
                <div class="icon"><i class="fas fa-chart-bar"></i></div>
                <h3 id="totalPrograms">{{ number_format($summaryStats['totalPrograms'] ?? 0) }}</h3>
                <p>{{ $mode === 'training' ? 'Training Programs' : 'Mentorship Programs' }}</p>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="icon"><i class="fas fa-users"></i></div>
                <h3 id="totalParticipants">{{ number_format($summaryStats['totalParticipants'] ?? 0) }}</h3>
                <p>{{ $mode === 'training' ? 'Participants' : 'Mentees' }}</p>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="icon"><i class="fas fa-hospital"></i></div>
                <h3 id="totalFacilities">{{ number_format($summaryStats['totalFacilities'] ?? 0) }}</h3>
                <p>Facilities Involved</p>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="icon"><i class="fas fa-percentage"></i></div>
                <h3 id="facilityCoverage">{{ $summaryStats['facilityCoverage'] ?? 0 }}%</h3>
                <p>Facility Coverage</p>
            </div>
        </div>

    <!-- Dynamic Layout Based on Mode -->
        <div id="main-content" class="layout-{{ $mode }}">
        <!-- Training Mode Layout -->
            @if($mode === 'training')
                <div class="row">
            <!-- Training/Mentorship Programs List -->
                    <div class="col-lg-4 mb-4">
                        <div class="card"
                             data-intro="This panel lists all available training programs. Click on any program to filter the map and charts to show data specific to that program. Selected programs will be highlighted with a blue border and background."
                             data-step="5">
                            <div class="card-header">
                                <h5 id="programsTitle">Training Programs</h5>
                                <small class="text-muted">Click on a program to filter map and charts. <span id="programCount">{{ $trainingsList->count() }}</span> programs found</small>
                            </div>
                            <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                                <div id="trainingsList">
                                    @forelse($trainingsList as $training)
                                        <div class="training-card" data-training-id="{{ $training->id }}">
                                            <div class="training-title">{{ $training->title }}</div>

                                            <div class="training-meta">
                                                <div class="meta-item">
                                                    <span class="meta-label">Participants</span>
                                                    <span class="meta-value">{{ number_format($training->total_participants ?? 0) }}</span>
                                                </div>

                                                <div class="meta-item">
                                                    <span class="meta-label">Facilities</span>
                                                    <span class="meta-value">{{ number_format($training->facilities_count ?? 0) }}</span>
                                                </div>
                                            </div>

                                            <div class="training-info">
                                                <div class="training-badges">
                                                    @if($training->start_date)
                                                        <span class="training-badge">{{ \Carbon\Carbon::parse($training->start_date)->format('Y') }}</span>
                                                    @endif
                                            @if($training->identifier)
                                                        <span class="training-badge">{{ $training->identifier }}</span>
                                                    @endif
                                                </div>
                                                <i class="fas fa-chevron-right action-icon"></i>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="empty-state p-4">
                                            <div class="text-center">
                                                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                                <h6 class="text-muted">No Programs Found</h6>
                                                <p class="text-muted mb-0">No training programs available for {{ $selectedYear ?: 'the selected period' }}</p>
                                            </div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

            <!-- Kenya Map -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 id="mapTitle">Kenya Counties Training Coverage Map</h5>
                                <small class="text-muted" id="mapSubtitle">Click on a county to view facilities with training programs</small>
                            </div>
                            <div class="card-body">
                                <div class="map-container" style="position: relative;"
                                     data-intro="This interactive map shows training coverage across Kenyan counties. Colors indicate participation levels - darker green means higher participation, yellow is moderate, and gray means no training activity. Click any county to drill down into facilities and participants."
                                     data-step="6">
                                    <div id="kenyaMap" style="height: 500px; border-radius: 8px;"></div>
                                    
                                    <!-- Coverage Summary Overlay -->
                                    <div class="map-overlay" id="mapOverlay"
                                         data-intro="This summary panel shows real-time coverage statistics. It displays facility coverage (how many facilities are participating), county coverage (how many counties have programs), and overall coverage percentage. The numbers update automatically when you filter by programs."
                                         data-step="7">
                                        <div class="overlay-header">
                                            <i class="fas fa-chart-pie text-primary"></i>
                                            <h6>Coverage Summary</h6>
                                        </div>
                                        
                                        <!-- Facility Coverage -->
                                        <div class="coverage-section">
                                            <div class="coverage-icon">
                                                <i class="fas fa-hospital text-primary"></i>
                                            </div>
                                            <div class="coverage-content">
                                                <div class="coverage-label">Facility Coverage</div>
                                                <div class="coverage-fraction">
                                                    <span id="overlay-facilities-active">{{ $summaryStats['totalFacilities'] ?? 0 }}</span>/<span id="overlay-facilities-total">0</span> facilities
                                                </div>
                                                <div class="coverage-percentage">
                                                    <span id="overlay-facility-coverage">{{ number_format($summaryStats['facilityCoverage'] ?? 0, 1) }}</span>% coverage
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- County Coverage -->
                                        <div class="coverage-section">
                                            <div class="coverage-icon">
                                                <i class="fas fa-map-marked-alt text-success"></i>
                                            </div>
                                            <div class="coverage-content">
                                                <div class="coverage-label">County Coverage</div>
                                                <div class="coverage-fraction">
                                                    <span id="overlay-counties-active">0</span>/<span id="overlay-counties-total">47</span> counties
                                                </div>
                                                <div class="coverage-percentage">
                                                    <span id="overlay-county-coverage">0.0</span>% coverage
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Overall Coverage -->
                                        <div class="coverage-section overall-section">
                                            <div class="coverage-icon">
                                                <i class="fas fa-chart-line text-warning"></i>
                                            </div>
                                            <div class="coverage-content">
                                                <div class="coverage-label">Overall Coverage</div>
                                                <div class="coverage-percentage-large">
                                                    <span id="overlay-overall-coverage">{{ number_format($summaryStats['facilityCoverage'] ?? 0, 1) }}</span>% coverage
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Map Legend -->
                                    <div class="map-legend" id="mapLegend"
                                         data-intro="The legend explains the color coding on the map. Dark green indicates extremely high participation (300+ participants), light green is high participation (100-299), yellow is moderate (10-99), pink is minimal (1-9), and gray means no training activity in that county."
                                         data-step="8">
                                        <h6>Coverage Legend</h6>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #15803d;"></div>
                                            <span class="legend-label">Extremely High (300+)</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #22c55e;"></div>
                                            <span class="legend-label">High (100-299)</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #fef08a;"></div>
                                            <span class="legend-label">Moderate (10-99)</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #ffcccc;"></div>
                                            <span class="legend-label">Minimal (1-9)</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #9ca3af;"></div>
                                            <span class="legend-label">No Training</span>
                                        </div>
                                    </div>
                                    
                                    <div class="loading-overlay">
                                        <div class="text-center">
                                            <div class="spinner-border text-primary mb-2" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <div><strong>Loading map data...</strong></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        <!-- Mentorship Mode Layout -->
            @if($mode === 'mentorship')
        <!-- Map Section -->
                <div class="map-section">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 id="mapTitle">Kenya Counties Mentorship Coverage Map</h5>
                            <small class="text-muted" id="mapSubtitle">Click on a county to view facilities with mentorship programs</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="map-container h-100">
                                <div id="kenyaMap" style="height: 100%; min-height: 500px; border-radius: 0;"></div>
                                
                                <!-- Map Overlay for Mentorship Mode -->
                                <div class="map-overlay" id="mapOverlay">
                                    <h6>
                                        <i class="fas fa-user-friends text-success"></i>
                                        Mentorship Summary
                                    </h6>
                                    <div class="map-overlay-stats">
                                        <div class="overlay-stat">
                                            <span class="overlay-stat-value" id="overlay-counties">47</span>
                                            <p class="overlay-stat-label">Counties</p>
                                        </div>
                                        <div class="overlay-stat">
                                            <span class="overlay-stat-value" id="overlay-programs">{{ $summaryStats['totalPrograms'] ?? 0 }}</span>
                                            <p class="overlay-stat-label">Mentorships</p>
                                        </div>
                                        <div class="overlay-stat">
                                            <span class="overlay-stat-value" id="overlay-participants">{{ number_format($summaryStats['totalParticipants'] ?? 0) }}</span>
                                            <p class="overlay-stat-label">Mentees</p>
                                        </div>
                                        <div class="overlay-stat">
                                            <span class="overlay-stat-value" id="overlay-facilities">{{ $summaryStats['totalFacilities'] ?? 0 }}</span>
                                            <p class="overlay-stat-label">Facilities</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Map Legend for Mentorship Mode -->
                                <div class="map-legend" id="mapLegend">
                                    <h6>Coverage Levels</h6>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #15803d;"></div>
                                        <span class="legend-label">Extremely High (300+)</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #22c55e;"></div>
                                        <span class="legend-label">High (100-299)</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #fef08a;"></div>
                                        <span class="legend-label">Moderate (10-99)</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #ffcccc;"></div>
                                        <span class="legend-label">Minimal (1-9)</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #9ca3af;"></div>
                                        <span class="legend-label">No Mentorship</span>
                                    </div>
                                </div>
                                
                                <div class="loading-overlay">
                                    <div class="text-center">
                                        <div class="spinner-border text-success mb-2" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <div><strong>Loading map data...</strong></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

        <!-- County Sidebar Section -->
                <div class="sidebar-section">
                    <div class="county-sidebar">
                        <div class="sidebar-header">
                            <h5 id="sidebar-title">Select a County</h5>
                            <small class="text-muted">Click on a county on the map to view mentorship facilities</small>
                        </div>
                        <div class="sidebar-content" id="sidebar-content">
                            <div class="sidebar-empty">
                                <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No County Selected</h6>
                                <p class="text-muted mb-0">Click on a county on the map to view facilities with mentorship programs</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

    <!-- Charts Section (only for training mode) -->
        @if($mode === 'training')
            <div class="chart-grid"
                 data-intro="These analytics charts provide detailed insights into participation patterns. The Department chart shows which departments have the most participants, the Cadre chart shows distribution by professional roles, and the Facility Type chart shows coverage by different types of healthcare facilities. You can hover over chart elements for detailed information."
                 data-step="9">
                <!-- Department Chart with Better Container -->
                <div class="chart-container department-chart-container">
                    <h6 class="mb-3">
                        <i class="fas fa-building me-2 text-primary"></i>
                        Participants by Department
                        <small class="text-muted ms-2" id="deptChartSubtitle">Top departments by participation</small>
                    </h6>
                    <canvas id="departmentChart" style="max-height: 280px !important;"></canvas>
                </div>

                <!-- Cadre Chart with Better Container -->
                <div class="chart-container cadre-chart-container">
                    <h6 class="mb-3">
                        <i class="fas fa-user-md me-2 text-success"></i>
                        Participants by Cadre
                        <small class="text-muted ms-2" id="cadreChartSubtitle">Professional roles distribution</small>
                    </h6>
                    <canvas id="cadreChart" style="max-height: 280px !important;"></canvas>
                </div>

                <!-- Facility Type Chart with Better Container -->
                <div class="chart-container facility-chart-container">
                    <h6 class="mb-3">
                        <i class="fas fa-hospital me-2 text-warning"></i>
                        Coverage by Facility Type
                        <small class="text-muted ms-2" id="facilityChartSubtitle">Training penetration by facility category</small>
                    </h6>
                    <canvas id="facilityTypeChart" style="max-height: 280px !important; cursor: pointer;"></canvas>
                </div>
            </div>
        @endif
    </div>

<!-- Chart Data Script Tag -->
    <script type="application/json" id="chart-data">
        @json($chartData ?? [])
    </script>

@endsection

@section('page-scripts')
// Healthcare Training Dashboard - Complete JavaScript Implementation with Guided Tour
(function() {
    'use strict';

    // Dashboard state management
    const Dashboard = {
        state: {
            map: null,
            countyLayer: null,
            selectedTraining: null,
            selectedCounty: null,
            charts: {},
            currentMode: document.querySelector('meta[name="dashboard-mode"]')?.content || 'training',
            currentYear: document.querySelector('meta[name="dashboard-year"]')?.content || '',
            facilityTypeData: null,
            originalTrainingsList: null
        },

        // Coverage levels for map coloring
        coverageLevels: {
            EXTREMELY_HIGH: { min: 300, color: '#15803d', label: 'Extremely High' },
            HIGH: { min: 100, color: '#22c55e', label: 'High' },
            MODERATE: { min: 10, color: '#fef08a', label: 'Moderate' },
            MINIMAL: { min: 1, color: '#ffcccc', label: 'Minimal' },
            NONE: { min: 0, color: '#9ca3af', label: 'No Training' }
        },

        // Chart colors
        chartColors: [
            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
            '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
        ],

        // Utility functions
        getCoverageLevel(participants) {
            for (const level of Object.values(this.coverageLevels)) {
                if (participants >= level.min) return level;
            }
            return this.coverageLevels.NONE;
        },

        getCoverageLevelByPercentage(percentage) {
            if (percentage >= 80) return this.coverageLevels.EXTREMELY_HIGH;
            if (percentage >= 60) return this.coverageLevels.HIGH;
            if (percentage >= 40) return this.coverageLevels.MODERATE;
            if (percentage >= 20) return this.coverageLevels.MINIMAL;
            return this.coverageLevels.NONE;
        },

        getChartColor(index) {
            return this.chartColors[index % this.chartColors.length];
        },

        // Map management
        Map: {
            init() {
                if (Dashboard.state.map) {
                    Dashboard.state.map.remove();
                }

                Dashboard.state.map = L.map('kenyaMap', {
                    center: [-0.5, 37.5],
                    zoom: 7,
                    minZoom: 6,
                    maxZoom: 12,
                    maxBounds: [[-5.5, 33.0], [5.5, 42.0]],
                    maxBoundsViscosity: 1.0
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    opacity: 0.7
                }).addTo(Dashboard.state.map);

                this.loadCountyData();
            },

            async loadCountyData(filterTrainingId = null) {
                const loadingOverlay = document.querySelector('.loading-overlay');
                if (loadingOverlay) loadingOverlay.classList.add('show');

                try {
                    const params = new URLSearchParams();
                    params.set('mode', Dashboard.state.currentMode);
                    if (Dashboard.state.currentYear) params.set('year', Dashboard.state.currentYear);
                    if (filterTrainingId) params.set('training_id', filterTrainingId);

                    const response = await fetch(`/analytics/dashboard/geojson?${params}`);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const data = await response.json();

                    if (Dashboard.state.countyLayer) {
                        Dashboard.state.map.removeLayer(Dashboard.state.countyLayer);
                    }

                    Dashboard.state.countyLayer = L.geoJSON(data, {
                        style: feature => this.getCountyStyle(feature.properties),
                        onEachFeature: (feature, layer) => this.setupCountyInteractions(feature, layer)
                    });

                    Dashboard.state.countyLayer.addTo(Dashboard.state.map);
                    
                    const bounds = Dashboard.state.countyLayer.getBounds();
                    if (bounds.isValid()) {
                        Dashboard.state.map.fitBounds(bounds, { 
                            padding: [20, 20],
                            maxZoom: filterTrainingId ? 9 : 8
                        });
                    }

                    // Update overlay stats
                    this.updateMapOverlay(data);

                } catch (error) {
                    console.error('Map loading error:', error);
                    this.showError(`Failed to load map data: ${error.message}`);
                } finally {
                    if (loadingOverlay) loadingOverlay.classList.remove('show');
                }
            },

            updateMapOverlay(data) {
                const features = data.features || [];
                let totalCounties = 47; // Total counties in Kenya
                let activeFacilities = 0;
                let totalFacilities = 0;
                let activeCounties = 0;

                features.forEach(feature => {
                    const props = feature.properties;
                    
                    // Count active counties (counties with any training activity)
                    if (props.total_participants > 0 || props.total_programs > 0) {
                        activeCounties++;
                    }
                    
                    // Sum up totals
                    activeFacilities += props.facilities_with_programs || 0;
                    totalFacilities += props.total_facilities || 0;
                });

                // Calculate coverage percentages with 1 decimal place
                const facilityCoverage = totalFacilities > 0 ? 
                    ((activeFacilities / totalFacilities) * 100).toFixed(1) : '0.0';
                const countyCoverage = totalCounties > 0 ? 
                    ((activeCounties / totalCounties) * 100).toFixed(1) : '0.0';
                const overallCoverage = ((parseFloat(facilityCoverage) + parseFloat(countyCoverage)) / 2).toFixed(1);

                // Helper function to animate value updates
                function updateElement(elementId, value, isNumber = false) {
                    const element = document.getElementById(elementId);
                    if (element && element.textContent !== value.toString()) {
                        element.classList.add('updating');
                        element.textContent = isNumber ? value.toLocaleString() : value;
                        setTimeout(() => element.classList.remove('updating'), 300);
                    }
                }

                // Update Facility Coverage
                updateElement('overlay-facilities-active', activeFacilities, true);
                updateElement('overlay-facilities-total', totalFacilities, true);
                updateElement('overlay-facility-coverage', facilityCoverage);

                // Update County Coverage
                updateElement('overlay-counties-active', activeCounties, true);
                updateElement('overlay-counties-total', totalCounties, true);
                updateElement('overlay-county-coverage', countyCoverage);

                // Update Overall Coverage
                updateElement('overlay-overall-coverage', overallCoverage);
            },

            setupCountyInteractions(feature, layer) {
                const props = feature.properties;
                const tooltipContent = this.createTooltip(props);

                layer.bindTooltip(tooltipContent, {
                    permanent: false,
                    direction: 'center',
                    className: 'county-tooltip'
                });

                layer.on({
                    mouseover: (e) => {
                        const layer = e.target;
                        layer.setStyle({
                            weight: 4,
                            color: '#3b82f6',
                            fillOpacity: 0.9
                        });
                        layer.openTooltip();
                    },
                    mouseout: (e) => {
                        Dashboard.state.countyLayer.resetStyle(e.target);
                        e.target.closeTooltip();
                    },
                    click: (e) => {
                        const countyId = props.county_id;
                        if (countyId) {
                            if (Dashboard.state.currentMode === 'training') {
                                // Pass selected training ID to maintain context
                                this.navigateToCounty(countyId, Dashboard.state.selectedTraining);
                            } else if (Dashboard.state.currentMode === 'mentorship') { 
                                this.loadCountyMentorships(countyId);
                            }
                        }
                    }
                });
            },

            // Navigate to county with training context
            navigateToCounty(countyId, selectedTrainingId = null) {
                const params = new URLSearchParams({ mode: 'training' });
                if (Dashboard.state.currentYear) params.set('year', Dashboard.state.currentYear);
                
                // Include the selected training ID if available
                if (selectedTrainingId) {
                    params.set('training_id', selectedTrainingId);
                }
                
                window.location.href = `/analytics/dashboard/county/${countyId}?${params}`;
            },

            async loadCountyMentorships(countyId) {
                try {
                    const params = new URLSearchParams({ mode: 'mentorship' });
                    if (Dashboard.state.currentYear) params.set('year', Dashboard.state.currentYear);
                    
                    const response = await fetch(`/analytics/dashboard/county/${countyId}/mentorships?${params}`);
                    const data = await response.json();
                    
                    this.updateMentorshipSidebar(data);
                } catch (error) {
                    console.error('Error loading county mentorships:', error);
                }
            },

            updateMentorshipSidebar(data) {
                const sidebarTitle = document.getElementById('sidebar-title');
                const sidebarContent = document.getElementById('sidebar-content');
                
                if (sidebarTitle) {
                    sidebarTitle.textContent = `${data.county.name} County`;
                }
                
                if (sidebarContent) {
                    sidebarContent.innerHTML = `
                        <h6 class="mb-3">Facilities with Mentorships</h6>
                        
                        ${data.facilities.length > 0 ? 
                            data.facilities.map(facility => `
                                <div class="facility-item" data-facility-id="${facility.id}" data-county-id="${data.county.id}">
                                    <h6>${facility.name}</h6>
                                    <div class="facility-meta">
                                        <div class="meta-item">
                                            <div class="meta-value">${facility.mentorship_count}</div>
                                            <div class="meta-label">Mentorships</div>
                                        </div>
                                        <div class="meta-item">
                                            <div class="meta-value">${facility.total_mentees}</div>
                                            <div class="meta-label">Mentees</div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">${facility.facility_type} • ${facility.subcounty}</small>
                                        <i class="fas fa-arrow-right text-success"></i>
                                    </div>
                                </div>
                            `).join('')
                            : 
                            `<div class="sidebar-empty">
                                <i class="fas fa-hospital fa-2x text-muted mb-3"></i>
                                <h6 class="text-muted">No Mentorships Found</h6>
                                <p class="text-muted mb-0">No facilities in ${data.county.name} County have mentorship programs.</p>
                            </div>`
                        }
                    `;
                    
                    // Add click handlers for facility items
                    this.setupFacilityClickHandlers();
                }
            },

            setupFacilityClickHandlers() {
                document.querySelectorAll('.facility-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const facilityId = this.dataset.facilityId;
                        const countyId = this.dataset.countyId;
                        if (facilityId && countyId) {
                            const params = new URLSearchParams({ mode: 'mentorship' });
                            if (Dashboard.state.currentYear) params.set('year', Dashboard.state.currentYear);
                            window.location.href = `/analytics/dashboard/county/${countyId}/facility/${facilityId}/mentorships?${params}`;
                        }
                    });
                });
            },

            getCountyStyle(properties) {
                const participants = properties.total_participants || 0;
                const level = Dashboard.getCoverageLevel(participants);

                return {
                    fillColor: level.color,
                    weight: 2,
                    opacity: 1,
                    color: 'white',
                    fillOpacity: 0.8
                };
            },

            createTooltip(properties) {
                const countyName = properties.county_name || 'Unknown County';
                const programs = properties.total_programs || 0;
                const participants = properties.total_participants || 0;
                const facilities = properties.facilities_with_programs || 0;
                const totalFacilities = properties.total_facilities || 0;
                const coverage = properties.coverage_percentage || 0;
             
                const level = Dashboard.getCoverageLevel(participants);
                const programLabel = Dashboard.state.currentMode === 'training' ? 'Training Programs' : 'Mentorship Programs';
                const participantLabel = Dashboard.state.currentMode === 'training' ? 'Participants' : 'Mentees';

                return `
                    <div style="min-width: 200px;">
                        <h6 style="margin-bottom: 8px; font-weight: bold;">${countyName} County</h6>
                        <div style="margin-bottom: 8px; text-align: center;">
                            <span style="background: ${level.color}; color: ${participants > 50 ? 'white' : 'black'}; 
                                         padding: 4px 8px; border-radius: 12px; font-weight: bold; font-size: 12px;">
                                ${level.label} - ${participants} ${participantLabel}
                            </span>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px;">
                            <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                                <div style="font-weight: bold; color: #3b82f6;">${programs}</div>
                                <div>${programLabel}</div>
                            </div>
                            <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                                <div style="font-weight: bold; color: #3b82f6;">${participants}</div>
                                <div>${participantLabel}</div>
                            </div>
                            <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                                <div style="font-weight: bold; color: #3b82f6;">${facilities}/${totalFacilities}</div>
                                <div>Facilities</div>
                            </div>
                            <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                                <div style="font-weight: bold; color: #3b82f6;">${coverage}%</div>
                                <div>Coverage</div>
                            </div>
                        </div>
                    </div>
                `;
            },

            showError(message) {
                const mapContainer = document.getElementById('kenyaMap');
                if (mapContainer) {
                    mapContainer.innerHTML = `
                        <div class="alert alert-danger h-100 d-flex align-items-center justify-content-center">
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                                <h6>Map Error</h6>
                                <p>${message}</p>
                                <button class="btn btn-sm btn-outline-danger" onclick="Dashboard.Map.init()">
                                    <i class="fas fa-redo"></i> Retry
                                </button>
                            </div>
                        </div>
                    `;
                }
            }
        },

        // Chart management (only for training mode)
        Charts: {
            init() {
                if (Dashboard.state.currentMode !== 'training') return;
                
                const chartDataElement = document.getElementById('chart-data');
                let chartData = {};
                
                if (chartDataElement) {
                    try {
                        chartData = JSON.parse(chartDataElement.textContent);
                    } catch (e) {
                        console.error('Error parsing chart data:', e);
                        return;
                    }
                }
                
                this.destroyExisting();
                this.createDepartmentChart(chartData);
                this.createCadreChart(chartData);
                this.createFacilityChart(chartData);
            },

            destroyExisting() {
                Object.values(Dashboard.state.charts).forEach(chart => {
                    if (chart && typeof chart.destroy === 'function') {
                        chart.destroy();
                    }
                });
                Dashboard.state.charts = {};
            },

            createDepartmentChart(chartData) {
                const ctx = document.getElementById('departmentChart');
                if (!ctx || !chartData.departments) return;

                const data = chartData.departments
                    .map(d => ({
                        name: d.name,
                        count: d.count || 0,
                        coverage: d.coverage_percentage || 0
                    }))
                    .sort((a, b) => b.count - a.count);

                Dashboard.state.charts.department = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(d => d.name),
                        datasets: [{
                            label: 'Participants',
                            data: data.map(d => d.count),
                            backgroundColor: data.map((d, index) => Dashboard.getChartColor(index)),
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        const item = data[context.dataIndex];
                                        const level = Dashboard.getCoverageLevelByPercentage(item.coverage);
                                        return `${item.name}: ${item.count} participants (${level.label} - ${item.coverage}%)`;
                                    }
                                }
                            }
                        },
                        scales: { 
                            y: { beginAtZero: true }
                        }
                    }
                });
            },

            createCadreChart(chartData) {
                const ctx = document.getElementById('cadreChart');
                if (!ctx || !chartData.cadres) return;

                const data = chartData.cadres
                    .map(c => ({
                        name: c.name,
                        count: c.count || 0,
                        coverage: c.coverage_percentage || 0
                    }))
                    .sort((a, b) => b.count - a.count);

                Dashboard.state.charts.cadre = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.map(c => c.name),
                        datasets: [{
                            data: data.map(c => c.count),
                            backgroundColor: data.map((c, index) => Dashboard.getChartColor(index))
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    padding: 8,
                                    generateLabels: (chart) => {
                                        const original = Chart.defaults.plugins.legend.labels.generateLabels;
                                        const labels = original.call(this, chart);
                                        
                                        labels.forEach((label, index) => {
                                            if (data[index]) {
                                                const level = Dashboard.getCoverageLevelByPercentage(data[index].coverage);
                                                label.text += ` (${level.label})`;
                                            }
                                        });
                                        
                                        return labels;
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        const item = data[context.dataIndex];
                                        const level = Dashboard.getCoverageLevelByPercentage(item.coverage);
                                        return `${item.name}: ${item.count} participants (${level.label} - ${item.coverage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            },

            createFacilityChart(chartData) {
                const ctx = document.getElementById('facilityTypeChart');
                if (!ctx || !chartData.facilityTypes) return;

                const data = chartData.facilityTypes
                    .map(f => ({
                        name: f.name,
                        coverage: f.coverage_percentage || 0,
                        total_facilities: f.total_facilities || 0,
                        active_facilities: f.active_facilities || 0,
                        total_participants: f.total_participants || 0
                    }))
                    .sort((a, b) => b.coverage - a.coverage);

                Dashboard.state.charts.facilityType = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(f => f.name),
                        datasets: [{
                            label: 'Coverage %',
                            data: data.map(f => f.coverage),
                            backgroundColor: data.map((f, index) => Dashboard.getChartColor(index)),
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        const item = data[context.dataIndex];
                                        const level = Dashboard.getCoverageLevelByPercentage(item.coverage);
                                        return `${item.name}: ${level.label} Coverage (${item.coverage}%)`;
                                    }
                                }
                            }},
                        scales: { 
                            y: { 
                                beginAtZero: true, 
                                max: 100,
                                ticks: {
                                    callback: (value) => value + '%'
                                }
                            }
                        }
                    }
                });
            },

            async update(trainingId = null) {
                try {
                    const params = new URLSearchParams();
                    params.set('mode', Dashboard.state.currentMode);
                    if (Dashboard.state.currentYear) params.set('year', Dashboard.state.currentYear);
                    if (trainingId) params.set('training_id', trainingId);

                    const response = await fetch(`/analytics/dashboard/training-data?${params}`);
                    const result = await response.json();
                    
                    if (result.success && result.chartData) {
                        this.updateData(result.chartData);
                        if (result.summaryStats) this.updateSummaryStats(result.summaryStats);
                    }
                } catch (error) {
                    console.error('Error updating charts:', error);
                }
            },

            updateData(data) {
                if (Dashboard.state.charts.department && data.departments) {
                    const sortedData = data.departments
                        .map(d => ({ name: d.name, count: d.count || 0, coverage: d.coverage_percentage || 0 }))
                        .sort((a, b) => b.count - a.count);
                    
                    const chart = Dashboard.state.charts.department;
                    chart.data.labels = sortedData.map(d => d.name);
                    chart.data.datasets[0].data = sortedData.map(d => d.count);
                    chart.data.datasets[0].backgroundColor = sortedData.map((d, index) => Dashboard.getChartColor(index));
                    chart.update('none');
                }

                if (Dashboard.state.charts.cadre && data.cadres) {
                    const sortedData = data.cadres
                        .map(c => ({ name: c.name, count: c.count || 0, coverage: c.coverage_percentage || 0 }))
                        .sort((a, b) => b.count - a.count);
                    
                    const chart = Dashboard.state.charts.cadre;
                    chart.data.labels = sortedData.map(c => c.name);
                    chart.data.datasets[0].data = sortedData.map(c => c.count);
                    chart.data.datasets[0].backgroundColor = sortedData.map((c, index) => Dashboard.getChartColor(index));
                    chart.update('none');
                }

                if (Dashboard.state.charts.facilityType && data.facilityTypes) {
                    const sortedData = data.facilityTypes
                        .map(f => ({ 
                            name: f.name, 
                            coverage: f.coverage_percentage || 0
                        }))
                        .sort((a, b) => b.coverage - a.coverage);
                    
                    const chart = Dashboard.state.charts.facilityType;
                    chart.data.labels = sortedData.map(f => f.name);
                    chart.data.datasets[0].data = sortedData.map(f => f.coverage);
                    chart.data.datasets[0].backgroundColor = sortedData.map((f, index) => Dashboard.getChartColor(index));
                    chart.update('none');
                }
            },

            updateSummaryStats(stats) {
                const elements = {
                    totalPrograms: document.getElementById('totalPrograms'),
                    totalParticipants: document.getElementById('totalParticipants'),
                    totalFacilities: document.getElementById('totalFacilities'),
                    facilityCoverage: document.getElementById('facilityCoverage')
                };
                
                if (elements.totalPrograms) elements.totalPrograms.textContent = (stats.totalPrograms || 0).toLocaleString();
                if (elements.totalParticipants) elements.totalParticipants.textContent = (stats.totalParticipants || 0).toLocaleString();
                if (elements.totalFacilities) elements.totalFacilities.textContent = (stats.totalFacilities || 0).toLocaleString();
                if (elements.facilityCoverage) elements.facilityCoverage.textContent = (stats.facilityCoverage || 0) + '%';
            }
        },

        // Training selection management (only for training mode)
        Training: {
            select(trainingId, trainingTitle) {
                if (Dashboard.state.currentMode !== 'training') return;
                
                Dashboard.state.selectedTraining = trainingId;
                
                // Update visual selection
                document.querySelectorAll('.training-card').forEach(card => {
                    card.classList.toggle('selected', card.dataset.trainingId == trainingId);
                });
                
                // Update program filter to match selection
                const programFilter = document.getElementById('program-filter');
                if (programFilter) {
                    programFilter.value = trainingId;
                }
                
                this.updateTitles(trainingTitle, true);
                this.updateChartSubtitles(true);
                Dashboard.Map.loadCountyData(trainingId);
                
                // Update charts if they exist
                if (typeof Chart !== 'undefined') {
                    Dashboard.Charts.update(trainingId);
                }
                
                this.updateInsights(trainingTitle);
            },

            clear() {
                if (Dashboard.state.currentMode !== 'training') return;
                
                Dashboard.state.selectedTraining = null;
                
                // Clear visual selection
                document.querySelectorAll('.training-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                // Clear program filter
                const programFilter = document.getElementById('program-filter');
                if (programFilter) {
                    programFilter.value = '';
                }
                
                this.updateTitles(null, false);
                this.updateChartSubtitles(false);
                Dashboard.Map.loadCountyData();
                
                // Update charts
                if (typeof Chart !== 'undefined') {
                    Dashboard.Charts.update();
                }
                
                this.updateInsights();
            },

            updateTitles(trainingTitle, isFiltered) {
                const mapTitle = document.getElementById('mapTitle');
                const mapSubtitle = document.getElementById('mapSubtitle');
                
                if (isFiltered && trainingTitle) {
                    if (mapTitle) mapTitle.textContent = `${trainingTitle} - County Coverage`;
                    if (mapSubtitle) mapSubtitle.textContent = 'Counties participating in this training program - Click to view facilities';
                } else {
                    if (mapTitle) mapTitle.textContent = 'Kenya Counties Training Coverage Map';
                    if (mapSubtitle) mapSubtitle.textContent = 'Click on a county to view facilities with training programs';
                }
            },

            updateChartSubtitles(filtered) {
                const subtitles = {
                    deptChartSubtitle: filtered ? 'Filtered by selected program' : 'Top departments by participation',
                    cadreChartSubtitle: filtered ? 'Filtered by selected program' : 'Professional roles distribution',
                    facilityChartSubtitle: filtered ? 'Filtered by selected program - Click for county breakdown' : 'Training penetration by facility category - Click for county breakdown'
                };
                
                Object.entries(subtitles).forEach(([id, text]) => {
                    const element = document.getElementById(id);
                    if (element) element.textContent = text;
                });
            },

            updateInsights(selectedTrainingTitle = null) {
                const container = document.getElementById('insightsContent');
                if (!container) return;
                
                if (selectedTrainingTitle) {
                    container.innerHTML = `
                        <div class="insight-item">
                            <i class="fas fa-filter text-primary me-2"></i>
                            <small><strong>Filtered:</strong> ${selectedTrainingTitle}</small>
                        </div>
                        <div class="insight-item">
                            <i class="fas fa-map-marked text-info me-2"></i>
                            <small>Map shows program-specific counties</small>
                        </div>
                        <div class="insight-item">
                            <i class="fas fa-chart-bar text-success me-2"></i>
                            <small>Charts filtered to program data</small>
                        </div>
                        <div class="insight-item">
                            <i class="fas fa-hospital text-warning me-2"></i>
                            <small>Click counties to view participating facilities</small>
                        </div>
                    `;
                } else {
                    // Generate dynamic insights based on current data
                    const stats = this.getCurrentStats();
                    container.innerHTML = `
                        <div class="insight-item">
                            <i class="fas fa-chart-line text-success me-2"></i>
                            <small>${stats.totalPrograms} ${Dashboard.state.currentMode} programs nationwide</small>
                        </div>
                        <div class="insight-item">
                            <i class="fas fa-users text-primary me-2"></i>
                            <small>${stats.totalParticipants} healthcare workers engaged</small>
                        </div>
                        <div class="insight-item">
                            <i class="fas fa-hospital text-warning me-2"></i>
                            <small>Click counties to view facilities</small>
                        </div>
                    `;
                }
            },

            getCurrentStats() {
                return {
                    totalPrograms: document.getElementById('totalPrograms')?.textContent || '0',
                    totalParticipants: document.getElementById('totalParticipants')?.textContent || '0',
                    facilityCoverage: document.getElementById('facilityCoverage')?.textContent || '0%'
                };
            }
        },

        // Search and Filter functionality
        Search: {
            init() {
                // Store original training list for filtering
                const trainingsList = document.getElementById('trainingsList');
                if (trainingsList) {
                    Dashboard.state.originalTrainingsList = trainingsList.innerHTML;
                }

                this.setupSearchFilter();
                this.setupProgramCounter();
            },

            setupSearchFilter() {
                const searchFilter = document.getElementById('search-filter');
                if (!searchFilter) return;

                let searchTimeout;
                searchFilter.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.filterTrainings(e.target.value.toLowerCase());
                    }, 300);
                });
            },

            setupProgramCounter() {
                // Update program counter when visibility changes
                const observer = new MutationObserver(() => {
                    this.updateProgramCounter();
                });

                const trainingsList = document.getElementById('trainingsList');
                if (trainingsList) {
                    observer.observe(trainingsList, {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['style']
                    });
                }
            },

            filterTrainings(searchTerm) {
                const trainingCards = document.querySelectorAll('.training-card');
                let visibleCount = 0;

                trainingCards.forEach(card => {
                    const title = card.querySelector('.training-title')?.textContent?.toLowerCase() || '';
                    const identifier = card.querySelector('.training-badge')?.textContent?.toLowerCase() || '';
                    
                    const isVisible = !searchTerm || 
                        title.includes(searchTerm) || 
                        identifier.includes(searchTerm);

                    card.style.display = isVisible ? 'block' : 'none';
                    
                    if (isVisible) visibleCount++;
                });

                this.updateProgramCounter(visibleCount);
                this.showNoResultsMessage(visibleCount === 0 && searchTerm);
            },

            updateProgramCounter(count = null) {
                const programCount = document.getElementById('programCount');
                if (!programCount) return;

                if (count === null) {
                    const visibleCards = document.querySelectorAll('.training-card:not([style*="display: none"])');
                    count = visibleCards.length;
                }

                programCount.textContent = count;
            },

            showNoResultsMessage(show) {
                const trainingsList = document.getElementById('trainingsList');
                if (!trainingsList) return;

                let noResultsMsg = trainingsList.querySelector('.no-search-results');
                
                if (show && !noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'no-search-results empty-state p-4';
                    noResultsMsg.innerHTML = `
                        <div class="text-center">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No Programs Found</h6>
                            <p class="text-muted mb-0">Try adjusting your search terms</p>
                        </div>
                    `;
                    trainingsList.appendChild(noResultsMsg);
                } else if (!show && noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        },

        // Event handling
        Events: {
            init() {
                this.setupModeButtons();
                this.setupFilters();
                this.setupResponsive();
                
                if (Dashboard.state.currentMode === 'training') {
                    this.setupTrainingCards();
                }
            },

            setupTrainingCards() {
                // Use event delegation for training cards
                const trainingsList = document.getElementById('trainingsList');
                if (trainingsList) {
                    trainingsList.addEventListener('click', (e) => {
                        const card = e.target.closest('.training-card');
                        if (!card) return;

                        e.preventDefault();
                        e.stopPropagation();
                        
                        const trainingId = card.dataset.trainingId;
                        const trainingTitle = card.querySelector('.training-title')?.textContent?.trim();
                        
                        if (card.classList.contains('selected')) {
                            Dashboard.Training.clear();
                        } else {
                            Dashboard.Training.select(trainingId, trainingTitle);
                        }
                    });
                }
            },

            setupModeButtons() {
                document.querySelectorAll('.mode-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const mode = btn.dataset.mode;
                        this.switchMode(mode);
                    });
                });
            },

            setupFilters() {
                // Year filter
                const yearFilter = document.getElementById('year-filter');
                if (yearFilter) {
                    yearFilter.addEventListener('change', () => {
                        const url = new URL(window.location);
                        if (yearFilter.value) {
                            url.searchParams.set('year', yearFilter.value);
                        } else {
                            url.searchParams.delete('year');
                        }
                        url.searchParams.set('mode', Dashboard.state.currentMode);
                        window.location.href = url.toString();
                    });
                }

                // Program filter
                const programFilter = document.getElementById('program-filter');
                if (programFilter) {
                    programFilter.addEventListener('change', () => {
                        if (programFilter.value) {
                            const card = document.querySelector(`[data-training-id="${programFilter.value}"]`);
                            if (card) {
                                const title = card.querySelector('.training-title')?.textContent?.trim();
                                Dashboard.Training.select(programFilter.value, title);
                                
                                // Scroll to selected card
                                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }
                        } else {
                            Dashboard.Training.clear();
                        }
                    });
                }

                // Clear filters
                const clearFilters = document.getElementById('clear-filters');
                if (clearFilters) {
                    clearFilters.addEventListener('click', () => {
                        if (programFilter) programFilter.value = '';
                        const searchFilter = document.getElementById('search-filter');
                        if (searchFilter) {
                            searchFilter.value = '';
                            Dashboard.Search.filterTrainings('');
                        }
                        Dashboard.Training.clear();
                    });
                }
            },

            setupResponsive() {
                let resizeTimer;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(() => {
                        if (Dashboard.state.map) {
                            Dashboard.state.map.invalidateSize();
                        }
                        Object.values(Dashboard.state.charts).forEach(chart => {
                            if (chart && chart.resize) chart.resize();
                        });
                    }, 250);
                });
            },

            switchMode(mode) {
                if (mode === Dashboard.state.currentMode) return;
                
                const url = new URL(window.location);
                url.searchParams.set('mode', mode);
                if (Dashboard.state.currentYear) url.searchParams.set('year', Dashboard.state.currentYear);
                window.location.href = url.toString();
            }
        },

        // Layout management
        Layout: {
            init() {
                this.updateLayout();
            },

            updateLayout() {
                const mainContent = document.getElementById('main-content');
                if (mainContent) {
                    mainContent.className = `layout-${Dashboard.state.currentMode}`;
                }

                // Update map sizing based on mode
                setTimeout(() => {
                    if (Dashboard.state.map) {
                        Dashboard.state.map.invalidateSize();
                    }
                }, 100);
            }
        },

        // GUIDED TOUR IMPLEMENTATION
        Tour: {
            init() {
                if (typeof introJs === 'undefined') {
                    console.warn('Intro.js not loaded - guided tour not available');
                    return;
                }

                this.setupTour();
                this.checkFirstTimeUser();
            },

            setupTour() {
                // Configure Intro.js
                this.tour = introJs();
                
                this.tour.setOptions({
                    showStepNumbers: true,
                    showBullets: false,
                    showProgress: true,
                    exitOnOverlayClick: false,
                    exitOnEsc: true,
                    nextLabel: 'Next →',
                    prevLabel: '← Back',
                    doneLabel: 'Finish Tour',
                    skipLabel: 'Skip Tour',
                    tooltipPosition: 'auto',
                    positionPrecedence: ['bottom', 'top', 'right', 'left']
                });

                // Setup event handlers
                this.tour.oncomplete(() => {
                    this.markTourCompleted();
                    setTimeout(() => {
                        this.showCompletionMessage();
                    }, 500);
                });

                this.tour.onexit(() => {
                    this.markTourCompleted();
                });

                // Manual tour start button
                const startTourBtn = document.getElementById('startTourBtn');
                if (startTourBtn) {
                    startTourBtn.addEventListener('click', () => {
                        this.startGuidedTour();
                    });
                }
            },

            checkFirstTimeUser() {
                if (this.isFirstTimeVisitor()) {
                    setTimeout(() => {
                        this.showWelcomePrompt();
                    }, 1500); // Delay to ensure page is fully loaded
                }
            },

            isFirstTimeVisitor() {
                return !localStorage.getItem('healthcare_dashboard_tour_completed');
            },

            markTourCompleted() {
                localStorage.setItem('healthcare_dashboard_tour_completed', 'true');
                localStorage.setItem('healthcare_dashboard_tour_date', new Date().toISOString());
            },

            showWelcomePrompt() {
                const shouldStartTour = confirm(
                    "Welcome to the Healthcare Analytics Dashboard!\n\n" +
                    "Would you like to take a quick guided tour to learn how to use the dashboard effectively?\n\n" +
                    "The tour takes about 2-3 minutes and will help you understand all the features."
                );
                
                if (shouldStartTour) {
                    this.startGuidedTour();
                } else {
                    this.markTourCompleted();
                }
            },

            startGuidedTour() {
                // Ensure filters are collapsed for tour
                const filtersCollapse = document.getElementById('filtersCollapse');
                if (filtersCollapse && filtersCollapse.classList.contains('show')) {
                    filtersCollapse.classList.remove('show');
                }

                // Clear any selected training for a clean tour
                if (Dashboard.state.currentMode === 'training') {
                    Dashboard.Training.clear();
                }

                // Start the tour
                this.tour.start();
            },

            showCompletionMessage() {
                alert(
                    "🎉 Tour completed! You're now ready to explore the Healthcare Analytics Dashboard.\n\n" +
                    "Tips:\n" +
                    "• Click on counties to drill down into facilities\n" +
                    "• Select training programs to filter the data\n" +
                    "• Use filters to narrow your analysis\n" +
                    "• Switch between Training and Mentorship modes\n\n" +
                    "You can restart this tour anytime by clicking the 'Take Tour' button."
                );
            },

            resetTour() {
               
                localStorage.removeItem('healthcare_dashboard_tour_completed');
                localStorage.removeItem('healthcare_dashboard_tour_date');
                console.log('Tour reset. Refresh the page to see the first-time user experience.');
            }
        },

        // Main initialization
        init() {
            if (typeof L === 'undefined') {
                console.error('Leaflet library not loaded');
                return;
            }
            
            if (Dashboard.state.currentMode === 'training' && typeof Chart === 'undefined') {
                console.error('Chart.js library not loaded for training mode');
                // Don't return, just skip charts
            }
            
            this.Layout.init();
            this.Map.init();
            
            if (Dashboard.state.currentMode === 'training' && typeof Chart !== 'undefined') {
                this.Charts.init();
                this.Search.init();
            }
            
            this.Events.init();
            this.Tour.init(); // Initialize guided tour
            
            console.log(`Healthcare ${Dashboard.state.currentMode} Dashboard initialized successfully`);
        }
    };

    // Global function exports for external access
    window.toggleInsights = () => {
        const panel = document.getElementById('insightsPanel');
        if (panel) panel.classList.toggle('show');
    };
    
    window.toggleDetailedInsights = function() {
        const detailedInsights = document.getElementById('detailedInsights');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (detailedInsights && toggleIcon) {
            const isHidden = detailedInsights.style.display === 'none';
            
            detailedInsights.style.display = isHidden ? 'block' : 'none';
            toggleIcon.className = isHidden ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
            
            if (isHidden) {
                detailedInsights.style.opacity = '0';
                detailedInsights.style.transform = 'translateY(-10px)';
                
                setTimeout(() => {
                    detailedInsights.style.transition = 'all 0.3s ease';
                    detailedInsights.style.opacity = '1';
                    detailedInsights.style.transform = 'translateY(0)';
                }, 10);
            }
        }
    };
    
    // API for external access and debugging
    window.DashboardAPI = {
        selectTraining: (id, title) => Dashboard.Training.select(id, title),
        clearTraining: () => Dashboard.Training.clear(),
        reloadMap: () => Dashboard.Map.loadCountyData(Dashboard.state.selectedTraining),
        reloadCharts: () => {
            if (Dashboard.state.currentMode === 'training' && typeof Chart !== 'undefined') {
                Dashboard.Charts.update(Dashboard.state.selectedTraining);
            }
        },
        getState: () => Dashboard.state,
        getCoverageLevels: () => Dashboard.coverageLevels,
        reinitialize: () => Dashboard.init(),
        searchTrainings: (term) => Dashboard.Search.filterTrainings(term),
        
        // Tour API
        startTour: () => Dashboard.Tour.startGuidedTour(),
        resetTour: () => Dashboard.Tour.resetTour(),
        isTourCompleted: () => !Dashboard.Tour.isFirstTimeVisitor(),
        
        getInsightsData: () => {
            const facilityCoverage = document.getElementById('facilityCoveragePercent')?.textContent || '0%';
            const countyCoverage = document.getElementById('countyCoveragePercent')?.textContent || '0%';
            const overallCoverage = document.getElementById('overallCoverage')?.textContent || '0%';
            
            return {
                facilityCoverage,
                countyCoverage,
                overallCoverage,
                quickInsight: document.getElementById('quickInsight')?.textContent || '',
                trendInsight: document.getElementById('trendInsight')?.textContent || '',
                geoInsight: document.getElementById('geoInsight')?.textContent || ''
            };
        }
    };

    // Initialize when DOM is ready
    Dashboard.init();

    // Ensure Bootstrap is available for modal functionality
    if (typeof bootstrap === 'undefined') {
        console.warn('Bootstrap is required for modal functionality');
    }

})();

// Global function to reset tour for testing purposes
function resetTour() {
    if (window.DashboardAPI) {
        window.DashboardAPI.resetTour();
    }
}

// Export resetTour globally for console access
window.resetTour = resetTour;
@endsection