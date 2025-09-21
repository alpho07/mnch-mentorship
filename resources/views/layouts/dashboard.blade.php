<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Training Analytics Dashboard')</title>

        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <!-- Chart.js -->
        <link href="https://unpkg.com/charts.css/dist/charts.min.css" rel="stylesheet">
        <!-- Leaflet CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
              crossorigin="" />
        <meta name="dashboard-mode" content="{{ $mode ?? 'training' }}">
        <meta name="dashboard-year" content="{{ $selectedYear ?? '' }}">
                


        @yield('additional-styles')
        

        <style>
            /* Base Styles */
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f8fafc;
                color: #334155;
                line-height: 1.6;
            }

            .container-fluid {
                max-width: 1400px;
                margin: 0 auto;
            }

            /* Breadcrumb */
            .breadcrumb-custom {
                background: linear-gradient(90deg, #f8fafc 0%, #e2e8f0 100%);
                border-radius: 12px;
                padding: 1rem 1.5rem;
                margin-bottom: 2rem;
                border: 1px solid #e2e8f0;
            }

            .breadcrumb-custom .breadcrumb {
                margin-bottom: 0;
                background: none;
                padding: 0;
            }

            .breadcrumb-custom a {
                color: #3b82f6;
                text-decoration: none;
                font-weight: 500;
            }

            .breadcrumb-custom a:hover {
                text-decoration: underline;
            }

            /* Header */
            .page-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 2rem;
                margin-bottom: 2rem;
                border-radius: 16px;
                position: relative;
                overflow: hidden;
            }

            .page-header::before {
                content: '';
                position: absolute;
                top: -50px;
                right: -50px;
                width: 200px;
                height: 200px;
                background: rgba(255,255,255,0.1);
                border-radius: 50%;
            }

            .page-header h1 {
                font-weight: 700;
                position: relative;
                z-index: 2;
            }

            .page-header p {
                opacity: 0.9;
                position: relative;
                z-index: 2;
            }

            /* Mode Toggle */
            .mode-toggle {
                background: #f1f5f9;
                border-radius: 12px;
                padding: 6px;
                display: flex;
                border: 1px solid #e2e8f0;
            }

            .mode-toggle button {
                padding: 10px 20px;
                border-radius: 8px;
                transition: all 0.2s;
                border: none;
                background: transparent;
                font-weight: 500;
                color: #64748b;
                flex: 1;
            }

            .mode-toggle button.active {
                background: white;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                color: #3b82f6;
            }

            .mode-indicator {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 25px;
                font-size: 0.875rem;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            }

            /* Stats Cards */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1.5rem;
                margin-bottom: 2rem;
            }

            .stats-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 16px;
                padding: 2rem;
                transition: transform 0.2s ease;
                border: none;
                position: relative;
                overflow: hidden;
                text-align: center;
            }

            .stats-card:hover {
                transform: translateY(-3px);
            }

            .stats-card::before {
                content: '';
                position: absolute;
                top: -30px;
                right: -30px;
                width: 100px;
                height: 100px;
                background: rgba(255,255,255,0.1);
                border-radius: 50%;
            }

            .stats-card h3 {
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 0.5rem;
                position: relative;
                z-index: 2;
            }

            .stats-card p {
                opacity: 0.9;
                margin-bottom: 0;
                position: relative;
                z-index: 2;
                font-weight: 500;
            }

            .stats-card .icon {
                position: absolute;
                top: 1rem;
                right: 1rem;
                font-size: 2rem;
                opacity: 0.3;
                z-index: 1;
            }

            /* Cards */
            .card {
                border: none;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                border-radius: 16px;
                transition: box-shadow 0.2s ease;
                overflow: hidden;
            }

            .card:hover {
                box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            }

            .card-header {
                background: white;
                border-bottom: 1px solid #e5e7eb;
                border-radius: 16px 16px 0 0 !important;
                padding: 1.5rem;
                position: relative;
            }

            .card-header::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 1.5rem;
                right: 1.5rem;
                height: 2px;
                background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 100%);
                border-radius: 1px;
            }

            .card-header h5 {
                margin-bottom: 0.25rem;
                font-weight: 600;
                color: #1e293b;
            }

            .card-header small {
                color: #64748b;
            }

            .card-body {
                padding: 1.5rem;
            }

            /* Form Controls */
            .form-select {
                border-radius: 10px;
                border: 1px solid #d1d5db;
                padding: 0.75rem 1rem;
                transition: all 0.2s ease;
                background-color: white;
            }

            .form-select:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }

            /* Buttons */
            .btn {
                border-radius: 10px;
                font-weight: 500;
                padding: 0.75rem 1.5rem;
                transition: all 0.2s ease;
                border: none;
            }

            .btn-primary {
                background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            }

            .btn-primary:hover {
                background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
            }

            .btn-outline-primary {
                border: 2px solid #3b82f6;
                color: #3b82f6;
                background: white;
            }

            .btn-outline-primary:hover {
                background: #3b82f6;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            }

            .btn-group .btn {
                border-radius: 0;
            }

            .btn-group .btn:first-child {
                border-radius: 10px 0 0 10px;
            }

            .btn-group .btn:last-child {
                border-radius: 0 10px 10px 0;
            }

            /* Progress Bars */
            .progress {
                height: 6px;
                border-radius: 3px;
                background-color: #e2e8f0;
                overflow: hidden;
            }

            .progress-bar {
                border-radius: 3px;
                transition: width 0.6s ease;
            }

            /* Badge */
            .badge {
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-weight: 600;
                font-size: 0.75rem;
            }

            /* Text Utilities */
            .fw-semibold {
                font-weight: 600;
            }

            .text-gradient {
                background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 3rem 1rem;
                color: #6b7280;
            }

            .empty-state .icon {
                font-size: 4rem;
                margin-bottom: 1rem;
                opacity: 0.5;
            }

            .empty-state h6 {
                margin-top: 1rem;
                color: #374151;
                font-weight: 600;
            }

            /* Heatmap */
            .heatmap-container {
                min-height: 400px;
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                border-radius: 12px;
                padding: 1.5rem;
                position: relative;
                overflow: hidden;
                border: 1px solid #e2e8f0;
            }

            .heatmap-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 1rem;
                height: 100%;
            }

            .heatmap-cell {
                padding: 1.25rem;
                border-radius: 12px;
                border: 1px solid #e5e7eb;
                background: white;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                min-height: 140px;
                position: relative;
                overflow: hidden;
            }

            .heatmap-cell:hover {
                transform: scale(1.05);
                box-shadow: 0 8px 20px rgba(0,0,0,0.15);
                z-index: 10;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 1rem;
                }

                .stats-card {
                    padding: 1.5rem;
                }

                .stats-card h3 {
                    font-size: 2rem;
                }

                .page-header {
                    padding: 1.5rem;
                }

                .page-header h1 {
                    font-size: 1.75rem;
                }

                .heatmap-grid {
                    grid-template-columns: 1fr;
                }

                .mode-toggle {
                    width: 100%;
                    margin-bottom: 1rem;
                }
            }

            @media (max-width: 576px) {
                .container-fluid {
                    padding-left: 1rem;
                    padding-right: 1rem;
                }

                .stats-grid {
                    grid-template-columns: 1fr;
                }

                .breadcrumb-custom {
                    padding: 0.75rem 1rem;
                    margin-bottom: 1rem;
                }

                .card-header,
                .card-body {
                    padding: 1rem;
                }

                .heatmap-cell {
                    min-height: 120px;
                    padding: 1rem;
                }
            }

            /* Animation */
            .fade-in {
                animation: fadeIn 0.5s ease-in;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @yield('custom-styles')
        </style>
    </head>
    <body>
    @if(isset($breadcrumbs) && count($breadcrumbs) > 1)
        <!-- Breadcrumb -->
        <div class="container-fluid px-4 py-3">
            <nav class="breadcrumb-custom">
                <ol class="breadcrumb mb-0">
                    @foreach($breadcrumbs as $crumb)
                    @if($crumb['url'])
                            <li class="breadcrumb-item">
                                <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
                            </li>
                        @else
                            <li class="breadcrumb-item active">{{ $crumb['name'] }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        </div>
    @endif

    @yield('content')

        <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
        <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
    crossorigin=""></script>

    <script>
            // Common year filter functionality
            function initYearFilter() {
                const yearFilter = document.getElementById('yearFilter');
                if (yearFilter) {
                    yearFilter.addEventListener('change', function() {
                        const url = new URL(window.location);
                        const selectedValue = this.value;
                    
                        if (selectedValue && selectedValue !== '') {
                            url.searchParams.set('year', selectedValue);
                        } else {
                            url.searchParams.delete('year');
                        }
                    
                        // Preserve mode if it exists
                        const currentMode = url.searchParams.get('mode') || '{{ $mode ?? "training" }}';
                        url.searchParams.set('mode', currentMode);
                    
                        window.location.href = url.toString();
                    });
                }
            }

            // Common mode switching functionality
            function switchMode(mode) {
                const url = new URL(window.location);
                url.searchParams.set('mode', mode);
            
                // Preserve year if it exists
                const currentYear = '{{ $selectedYear ?? "" }}';
                if (currentYear) {
                    url.searchParams.set('year', currentYear);
                }
            
                window.location.href = url.toString();
            }

            // Initialize common functionality
            document.addEventListener('DOMContentLoaded', function() {
                initYearFilter();
        @yield('page-scripts')
            });
    </script>

    @yield('scripts')
</body>
</html>