@extends('layouts.dashboard')

@section('title', 'Training Analytics Dashboard')

@section('content')
    <div class="container-fluid px-4">
    <!-- Header Section -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2 mb-1">Trainings/Mentorships Analytics Dashboard</h1>
                    <p class="mb-0">Discover insights and track progress across counties and facilities</p>
                </div>

            <!-- Controls -->
                <div class="d-flex gap-3 align-items-center flex-wrap">
                <!-- Mode Toggle -->
                    <div class="mode-toggle">
                        <button type="button" class="mode-btn {{ $mode === 'training' ? 'active' : '' }}" data-mode="training">
                        Trainings
                        </button>
                        <button type="button" class="mode-btn {{ $mode === 'mentorship' ? 'active' : '' }}" data-mode="mentorship">
                        Mentorships
                        </button>
                    </div>

                <!-- Year Filter -->
                    <select id="yearFilter" class="form-select" style="width: auto;">
                        <option value="" {{ empty($selectedYear) ? 'selected' : '' }}>All Years</option>
                    @foreach($availableYears ?? [] as $year)
                            <option value="{{ $year }}" {{ ($selectedYear ?? '') == $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

    <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stats-card">
                <div class="icon"><i class="fas fa-chart-bar"></i></div>
                <h3>{{ number_format($summaryStats['totalPrograms'] ?? 0) }}</h3>
                <p>{{ $mode === 'training' ? 'Training Programs' : 'Mentorship Programs' }}</p>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="icon"><i class="fas fa-users"></i></div>
                <h3>{{ number_format($summaryStats['totalParticipants'] ?? 0) }}</h3>
                <p>{{ $mode === 'training' ? 'Participants' : 'Mentees' }}</p>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="icon"><i class="fas fa-hospital"></i></div>
                <h3>{{ number_format($summaryStats['totalFacilities'] ?? 0) }}</h3>
                <p>Facilities Involved</p>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="icon"><i class="fas fa-hospital"></i></div>
                @php
                    $totalFacilities = \App\Models\Facility::count();
                    $facilitiesWithPrograms = $counties->sum('facilities_with_programs') ?? 0;
                    $facilityCoverageRate = $totalFacilities > 0 ? round(($facilitiesWithPrograms / $totalFacilities) * 100, 1) : 0;
                @endphp
                <h3>{{ $facilityCoverageRate }}%</h3>
                <p>Facility Coverage</p>
            </div>
        </div>

    <!-- Main Content -->
        <div class="row">
        <!-- Kenya Map -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Kenya Counties {{ $mode === 'training' ? 'Training' : 'Mentorship' }} Coverage Map</h5>
                        <small class="text-muted">Click on a county to explore detailed analytics. Hover for quick statistics.</small>
                    </div>
                    <div class="card-body">
                        <div id="kenyaMap" style="height: 500px; border-radius: 8px;"></div>

                    <!-- Map Legend -->
                        <div class="map-legend mt-3">
                            <h6 class="mb-2">{{ $mode === 'training' ? 'Training' : 'Mentorship' }} Participant Legend</h6>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #6b7280;"></span>
                                    <small>No {{ $mode === 'training' ? 'Training' : 'Mentorship' }}</small>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #ef4444;"></span>
                                    <small>Low {{ $mode === 'training' ? 'Training' : 'Mentorship' }}</small>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #f97316;"></span>
                                    <small>Moderate {{ $mode === 'training' ? 'Training' : 'Mentorship' }}</small>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #eab308;"></span>
                                    <small>High {{ $mode === 'training' ? 'Training' : 'Mentorship' }}</small>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #84cc16;"></span>
                                    <small>Very High {{ $mode === 'training' ? 'Training' : 'Mentorship' }}</small>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #22c55e;"></span>
                                    <small>Extremely High {{ $mode === 'training' ? 'Training' : 'Mentorship' }}</small>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #16a34a;"></span>
                                    <small>Maximum {{ $mode === 'training' ? 'Training' : 'Mentorship' }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- County List -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Counties Overview</h5>
                        <small class="text-muted">{{ ($counties ?? collect())->count() }} counties with {{ $mode }} activities</small>
                    </div>
                    <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                        @php
                            // Sort counties by coverage percentage (highest to lowest)
                            $sortedCounties = ($counties ?? collect())->sortByDesc('coverage_percentage');
                        @endphp
                        @forelse($sortedCounties as $county)
                            <div class="county-card border-bottom p-3" data-county-id="{{ $county->id }}">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-1 fw-bold">{{ $county->name }} County</h6>
                                    @php
                                $coverageClass = $county->coverage_percentage >= 71 ? 'bg-success' : 
                                                ($county->coverage_percentage >= 41 ? 'bg-warning' : 
                                                ($county->coverage_percentage >= 1 ? 'bg-secondary' : 'bg-danger'));
                            @endphp
                                    <span class="badge {{ $coverageClass }} text-white">
                                        {{ $county->coverage_percentage }}%
                                    </span>
                                </div>

                                <div class="row g-2 text-sm">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Facilities</small>
                                        <span class="fw-semibold">{{ number_format($county->facilities_with_programs) }}/{{ number_format($county->total_facilities) }}</span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">{{ $mode === 'training' ? 'Participants' : 'Mentees' }}</small>
                                        <span class="fw-semibold">{{ number_format($county->total_participants) }}</span>
                                    </div>
                                </div>

                                <div class="progress mt-2">
                                    <div class="progress-bar {{ str_replace('bg-', '', $coverageClass) }}" 
                                    style="width: {{ $county->coverage_percentage }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-state">
                                <div class="icon"><i class="fas fa-chart-bar"></i></div>
                                <h6>No Data Available</h6>
                                <p>No counties have {{ $mode }} activities for {{ $selectedYear ?: 'the selected period' }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('custom-styles')
.county-card {
transition: all 0.3s ease;
cursor: pointer;
border: 2px solid transparent !important;
}

.county-card:hover {
transform: translateY(-2px);
box-shadow: 0 4px 12px rgba(0,0,0,0.1);
border-color: #3b82f6 !important;
background-color: #f8fafc;
}

.text-sm {
font-size: 0.875rem;
}

.map-legend {
border-top: 1px solid #e5e7eb;
padding-top: 1rem;
}

.legend-item {
display: flex;
align-items: center;
gap: 0.5rem;
}

.legend-color {
width: 20px;
height: 20px;
border-radius: 4px;
border: 1px solid rgba(0,0,0,0.1);
}

.leaflet-popup-content {
margin: 8px 12px;
line-height: 1.4;
min-width: 220px;
}

.leaflet-popup-content h6 {
margin-bottom: 8px;
color: #1e293b;
font-weight: 600;
}

.leaflet-popup-content .popup-stats {
display: grid;
grid-template-columns: 1fr 1fr;
gap: 12px;
font-size: 0.875rem;
}

.leaflet-popup-content .popup-stat {
text-align: center;
padding: 8px;
background: #f8fafc;
border-radius: 6px;
border: 1px solid #e2e8f0;
}

.leaflet-popup-content .popup-stat-value {
display: block;
font-weight: 700;
font-size: 1.1rem;
color: #3b82f6;
margin-bottom: 2px;
}

.leaflet-popup-content .popup-stat-label {
display: block;
color: #374151;
font-size: 0.75rem;
font-weight: 600;
margin-bottom: 2px;
}

.leaflet-popup-content .popup-stat-detail {
display: block;
color: #6b7280;
font-size: 0.7rem;
font-style: italic;
}
@endsection

@section('page-scripts')

let map;
let countyLayer;
const currentMode = '{{ $mode ?? "training" }}';
const currentYear = '{{ $selectedYear ?? "" }}';

// Initialize map
function initializeMap() {
try {
// Check if map already exists
if (map) {
map.remove();
}

// Initialize map with enhanced zoom settings
map = L.map('kenyaMap', {
center: [-1.2921, 36.8219],
zoom: 6,
minZoom: 5,    // Prevent zooming out too far
maxZoom: 12,   // Allow zooming in for detail
zoomControl: true,
scrollWheelZoom: true,
doubleClickZoom: true
});

// Add tile layer
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
attribution: '© OpenStreetMap contributors',
maxZoom: 18
}).addTo(map);

console.log('Map initialized successfully');

// Load county data
loadCountyData();

} catch (error) {
console.error('Error initializing map:', error);
showMapError('Map failed to initialize. Please check if Leaflet is loaded properly.');
}
}

// Load county GeoJSON data
async function loadCountyData() {
try {
const params = new URLSearchParams();
if (currentMode) params.set('mode', currentMode);
if (currentYear) params.set('year', currentYear);

// Build the URL - adjust based on your route structure
const url = '{{ route("analytics.dashboard.index") }}/geojson?' + params.toString();
console.log('Fetching data from:', url);

const response = await fetch(url);

if (!response.ok) {
throw new Error(`HTTP ${response.status}: ${response.statusText}`);
}

const data = await response.json();
console.log('GeoJSON data received:', data);

if (!data.features || data.features.length === 0) {
console.warn('No county features found in response');
showMapWarning('No county data available for the selected filters.');
return;
}

// Clear existing county layer
if (countyLayer) {
map.removeLayer(countyLayer);
countyLayer = null;
}

// Add GeoJSON layer
countyLayer = L.geoJSON(data, {
style: function(feature) {
return getCountyStyle(feature.properties);
},
onEachFeature: function(feature, layer) {
setupCountyLayer(feature, layer);
}
});

// Add to map
countyLayer.addTo(map);

// Fit map to bounds if we have valid bounds
try {
const bounds = countyLayer.getBounds();
if (bounds.isValid()) {
map.fitBounds(bounds, { padding: [20, 20] });

// Add custom zoom controls after fitting bounds
addZoomButton();
}
} catch (boundsError) {
console.warn('Could not fit bounds:', boundsError);
}

console.log('County layer added successfully');

} catch (error) {
console.error('Error loading county data:', error);
showMapError(`Failed to load map data: ${error.message}`);
}
}

// Show map error message
function showMapError(message) {
const mapContainer = document.getElementById('kenyaMap');
if (mapContainer) {
mapContainer.innerHTML = `
    <div class="alert alert-danger h-100 d-flex align-items-center justify-content-center">
        <div class="text-center">
            <i class="fas fa-exclamation-triangle fa-3x mb-3 text-danger"></i>
            <h6>Map Loading Error</h6>
            <p class="mb-3">${message}</p>
            <button class="btn btn-sm btn-outline-danger" onclick="retryMapLoad()">
                <i class="fas fa-redo"></i> Retry
            </button>
        </div>
    </div>
`;
}
}

// Show map warning message
function showMapWarning(message) {
const mapContainer = document.getElementById('kenyaMap');
if (mapContainer) {
mapContainer.innerHTML = `
    <div class="alert alert-warning h-100 d-flex align-items-center justify-content-center">
        <div class="text-center">
            <i class="fas fa-info-circle fa-3x mb-3 text-warning"></i>
            <h6>No Data Available</h6>
            <p class="mb-0">${message}</p>
        </div>
    </div>
`;
}
}

// Retry map loading
function retryMapLoad() {
const mapContainer = document.getElementById('kenyaMap');
if (mapContainer) {
mapContainer.innerHTML = ''; // Clear error message
}
setTimeout(initializeMap, 500);
}

// Get county style based on number of participants
function getCountyStyle(properties) {
const participants = properties.total_participants || 0;
let fillColor = '#dc2626'; // Default red for no participants
let fillOpacity = 0.7;

// Color code by number of participants
if (participants >= 1000) {
fillColor = '#16a34a'; // Dark Green - Very High
fillOpacity = 0.8;
} else if (participants >= 500) {
fillColor = '#22c55e'; // Green - High
fillOpacity = 0.75;
} else if (participants >= 200) {
fillColor = '#84cc16'; // Light Green - Medium-High
fillOpacity = 0.7;
} else if (participants >= 100) {
fillColor = '#eab308'; // Yellow - Medium
fillOpacity = 0.7;
} else if (participants >= 50) {
fillColor = '#f97316'; // Orange - Low-Medium
fillOpacity = 0.7;
} else if (participants >= 1) {
fillColor = '#ef4444'; // Red - Low
fillOpacity = 0.7;
} else {
fillColor = '#6b7280'; // Gray - No participants
fillOpacity = 0.5;
}

return {
fillColor: fillColor,
weight: 2,
opacity: 1,
color: 'white',
dashArray: '3',
fillOpacity: fillOpacity
};
}

// Setup county layer interactions
function setupCountyLayer(feature, layer) {
const props = feature.properties;

// Create tooltip content
const tooltipContent = createTooltipContent(props);

// Bind tooltip
layer.bindTooltip(tooltipContent, {
permanent: false,
direction: 'center',
className: 'county-tooltip'
});

// Mouse events
layer.on({
mouseover: function(e) {
const layer = e.target;
layer.setStyle({
weight: 4,
color: '#3b82f6',
dashArray: '',
fillOpacity: 0.9
});
layer.openTooltip();
},
mouseout: function(e) {
if (countyLayer) {
countyLayer.resetStyle(e.target);
}
e.target.closeTooltip();
},
click: function(e) {
const countyId = props.county_id;
if (countyId) {
navigateToCounty(countyId);
} else {
console.warn('No county ID found for:', props.county_name || 'Unknown County');
}
}
});
}

// Create tooltip content
function createTooltipContent(properties) {
// Use the exact property structure from your GeoJSON file
const countyName = properties.county_name || properties.COUNTY || 'Unknown County';
const programs = properties.total_programs || 0;
const participants = properties.total_participants || 0;
const facilitiesWithPrograms = properties.facilities_with_programs || 0;
const totalFacilities = properties.total_facilities || 0;
const coverage = properties.coverage_percentage || 0;

const programLabel = currentMode === 'training' ? 'Training Programs' : 'Mentorship Programs';
const participantLabel = currentMode === 'training' ? 'Participants' : 'Mentees';

return `
    <div class="popup-content">
        <h6 class="mb-2">${countyName} County</h6>
        <div class="popup-stats">
            <div class="popup-stat">
                <span class="popup-stat-value">${programs}</span>
                <span class="popup-stat-label">${programLabel}</span>
                <small class="popup-stat-detail">${participants} ${participantLabel}</small>
            </div>
            <div class="popup-stat">
                <span class="popup-stat-value">${participants}</span>
                <span class="popup-stat-label">Participant Intensity</span>
                <small class="popup-stat-detail">Map Color Basis</small>
            </div>
            <div class="popup-stat">
                <span class="popup-stat-value">${facilitiesWithPrograms}/${totalFacilities}</span>
                <span class="popup-stat-label">Facilities Trained</span>
                <small class="popup-stat-detail">Active/Total</small>
            </div>
            <div class="popup-stat">
                <span class="popup-stat-value">${coverage}%</span>
                <span class="popup-stat-label">Training Coverage</span>
                <small class="popup-stat-detail">By Facility</small>
            </div>
        </div>
        <div class="text-center mt-2">
            <small class="text-muted">Click to explore county details</small>
        </div>
    </div>
`;
}

// Add zoom controls
function addZoomButton() {
// Create custom zoom control
const zoomControl = L.control({ position: 'topright' });

zoomControl.onAdd = function(map) {
const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');

div.innerHTML = `
    <a href="#" title="Zoom In Once" class="zoom-in-btn">
        <i class="fas fa-search-plus"></i>
    </a>
    <a href="#" title="Reset View" class="reset-view-btn">
        <i class="fas fa-home"></i>
    </a>
`;

div.style.backgroundColor = 'white';
div.style.backgroundClip = 'padding-box';

// Zoom in button
const zoomInBtn = div.querySelector('.zoom-in-btn');
zoomInBtn.style.width = '30px';
zoomInBtn.style.height = '30px';
zoomInBtn.style.display = 'flex';
zoomInBtn.style.alignItems = 'center';
zoomInBtn.style.justifyContent = 'center';
zoomInBtn.style.textDecoration = 'none';
zoomInBtn.style.color = '#333';
zoomInBtn.style.borderBottom = '1px solid #ccc';

zoomInBtn.onclick = function(e) {
e.preventDefault();
map.zoomIn(1);
return false;
};

// Reset view button
const resetBtn = div.querySelector('.reset-view-btn');
resetBtn.style.width = '30px';
resetBtn.style.height = '30px';
resetBtn.style.display = 'flex';
resetBtn.style.alignItems = 'center';
resetBtn.style.justifyContent = 'center';
resetBtn.style.textDecoration = 'none';
resetBtn.style.color = '#333';

resetBtn.onclick = function(e) {
e.preventDefault();
if (countyLayer && countyLayer.getBounds().isValid()) {
map.fitBounds(countyLayer.getBounds(), { padding: [20, 20] });
} else {
map.setView([-1.2921, 36.8219], 6);
}
return false;
};

return div;
};

zoomControl.addTo(map);
}

// Navigate to county page
function navigateToCounty(countyId) {
const params = new URLSearchParams({
mode: currentMode
});

if (currentYear) {
params.set('year', currentYear);
}

// Build URL manually to match your route structure
const baseUrl = '{{ url("/") }}/analytics/dashboard/county/' + countyId;
window.location.href = baseUrl + '?' + params.toString();
}

// Mode toggle
document.querySelectorAll('.mode-btn').forEach(btn => {
btn.addEventListener('click', (e) => {
switchMode(e.target.dataset.mode);
});
});

// County cards click
document.querySelectorAll('.county-card').forEach(card => {
card.addEventListener('click', (e) => {
const countyId = e.currentTarget.dataset.countyId;
if (countyId) {
navigateToCounty(countyId);
}
});
});

// Check if Leaflet is loaded
function checkLeafletLoaded() {
if (typeof L === 'undefined') {
console.error('Leaflet is not loaded');
showMapError('Leaflet library is not loaded. Please check your internet connection.');
return false;
}
return true;
}
initializeMap();
// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
console.log('DOM loaded, initializing map...');

if (!checkLeafletLoaded()) {
return;
}

// Add a slight delay to ensure all assets are loaded
setTimeout(function() {
initializeMap();
}, 1000);
});
@endsection