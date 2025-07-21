<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kenya Training Fire Heatmap</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .map-container { height: 640px; width: 100%; }
        .leaflet-container { background: #f9fafb; }
        .leaflet-popup-content { min-width: 220px !important; }
        .modal-bg {
            background: rgba(0,0,0,0.40);
        }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-3 text-gray-900">Kenya Training “Fire” Heatmap</h1>
        <div class="mb-3">
            <div class="flex flex-wrap items-center text-xs gap-2">
                <div class="flex items-center gap-1"><span class="block w-4 h-4 bg-gray-300 border rounded"></span> No Data: Review &amp; Audit</div>
                <div class="flex items-center gap-1"><span class="block w-4 h-4 bg-green-300 border rounded"></span> Low: Target Training</div>
                <div class="flex items-center gap-1"><span class="block w-4 h-4 bg-yellow-300 border rounded"></span> Moderate: Monitor/Refresher</div>
                <div class="flex items-center gap-1"><span class="block w-4 h-4 bg-orange-400 border rounded"></span> High: Sustain Quality</div>
                <div class="flex items-center gap-1"><span class="block w-4 h-4 bg-red-600 border rounded"></span> Very High: Share Success</div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow">
            <div class="p-6">
                <div class="mb-4">
                    <span class="inline-block align-middle mr-2">🔥</span>
                    <b>Counties colored by intensity, actionable popups, fire-style heatmap!</b>
                </div>
                <div id="kenya-heatmap" class="map-container rounded-lg border border-gray-200 bg-gray-50"></div>
            </div>
        </div>
    </div>

    <!-- Action Modal (reusable for all actions) -->
    <div id="actionModal" class="fixed inset-0 hidden items-center justify-center z-50 modal-bg">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-auto p-6 relative">
            <button id="modalCloseBtn" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 text-xl" title="Close">&times;</button>
            <div id="modalContent" class="space-y-4"></div>
        </div>
    </div>

    <!-- Libraries -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
    <script>
        const widgetData = @json($widget->getMapData());
        const geojsonUrl = "{{ asset('kenyan-counties.geojson') }}";

        // --- Modal Logic ---
        function showActionModal(html) {
            document.getElementById('modalContent').innerHTML = html;
            document.getElementById('actionModal').classList.remove('hidden');
            document.getElementById('actionModal').classList.add('flex');
        }
        function closeActionModal() {
            document.getElementById('actionModal').classList.remove('flex');
            document.getElementById('actionModal').classList.add('hidden');
        }
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('modalCloseBtn').onclick = closeActionModal;
            document.getElementById('actionModal').onclick = function(e) {
                if (e.target === this) closeActionModal();
            };

            // --- Map logic ---
            const map = L.map('kenya-heatmap', {
                center: [-0.7, 37.7],
                zoom: 6,
                minZoom: 5,
                maxZoom: 12,
                scrollWheelZoom: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            fetch(geojsonUrl)
                .then(r => r.json())
                .then(geojson => {
                    addWorldMask(map, geojson);

                    const colorIntensities = (widgetData.countyData || []).map(c => c.intensity);
                    const minColorIntensity = Math.min(...colorIntensities, 0);
                    const maxColorIntensity = Math.max(...colorIntensities, 1);
                    const nameToCounty = {};
                    (widgetData.countyData || []).forEach(c => {
                        nameToCounty[c.name.trim().toLowerCase()] = c;
                    });

                    function getIntensityCategory(intensity, min, max) {
                        if (intensity === 0) return "No Data";
                        if (intensity <= min + 0.15*(max-min)) return "Low";
                        if (intensity <= min + 0.35*(max-min)) return "Moderate";
                        if (intensity <= min + 0.7*(max-min)) return "High";
                        return "Very High";
                    }

                    // --- Action content templates for each intensity category ---
                    function getAction(category, countyName) {
                        switch(category) {
                            case "No Data":
                                return {
                                    msg: "No recent training data. <b>Urgently assess reporting or program reach.</b>",
                                    action: `<button class="bg-red-600 text-white px-2 py-1 rounded text-xs mt-1"
                                        onclick="showActionModal(
                                            '<h3 class=\\'font-bold text-lg\\'>Review Data Quality for ${countyName}</h3>\
                                            <p>Check DHIS2 reports for gaps. Contact the M&E officer for data audit.</p>\
                                            <button onclick=\\'closeActionModal()\\' class=\\'mt-4 px-3 py-1 bg-gray-300 rounded\\'>Done</button>')">Review Data Quality</button>`,
                                    icon: "❓"
                                };
                            case "Low":
                                return {
                                    msg: "Coverage is low. <b>Plan targeted training or mentorship sessions.</b>",
                                    action: `<button class="bg-orange-500 text-white px-2 py-1 rounded text-xs mt-1"
                                        onclick="showActionModal(
                                            '<h3 class=\\'font-bold text-lg\\'>Schedule Training for ${countyName}</h3>\
                                            <form class=\\'mt-2 space-y-2\\'>\
                                                <label class=\\'block text-sm\\'>Training Topic\
                                                <input class=\\'mt-1 block w-full border rounded p-1\\' placeholder=\\'e.g. Mentorship, Refresher\\'></label>\
                                                <label class=\\'block text-sm\\'>Suggested Date\
                                                <input type=\\'date\\' class=\\'mt-1 block w-full border rounded p-1\\'></label>\
                                                <button type=\\'button\\' class=\\'mt-3 px-4 py-1 bg-orange-600 text-white rounded\\' onclick=\\'closeActionModal()\\'>Submit</button>\
                                            </form>')">Schedule Training</button>`,
                                    icon: "🟠"
                                };
                            case "Moderate":
                                return {
                                    msg: "Moderate coverage. <b>Monitor progress, consider refresher training if needed.</b>",
                                    action: `<button class="bg-yellow-400 text-gray-900 px-2 py-1 rounded text-xs mt-1"
                                        onclick="showActionModal(
                                            '<h3 class=\\'font-bold text-lg\\'>Plan Refresher Training for ${countyName}</h3>\
                                            <p>Identify groups with low retention or upcoming program cycles. Notify county training coordinator.</p>\
                                            <button onclick=\\'closeActionModal()\\' class=\\'mt-4 px-3 py-1 bg-gray-300 rounded\\'>OK</button>')">Plan Refresher</button>`,
                                    icon: "🟡"
                                };
                            case "High":
                                return {
                                    msg: "High coverage. <b>Maintain quality and ensure retention.</b>",
                                    action: `<button class="bg-green-500 text-white px-2 py-1 rounded text-xs mt-1"
                                        onclick="showActionModal(
                                            '<h3 class=\\'font-bold text-lg\\'>Send Recognition in ${countyName}</h3>\
                                            <p>Recognize top trainers or facilities. Share appreciation via SMS/email or in meetings.</p>\
                                            <button onclick=\\'closeActionModal()\\' class=\\'mt-4 px-3 py-1 bg-gray-300 rounded\\'>Done</button>')">Send Recognition</button>`,
                                    icon: "🟢"
                                };
                            case "Very High":
                                return {
                                    msg: "Excellent coverage! <b>Share best practices with other counties.</b>",
                                    action: `<button class="bg-blue-600 text-white px-2 py-1 rounded text-xs mt-1"
                                        onclick="showActionModal(
                                            '<h3 class=\\'font-bold text-lg\\'>Share Success Story from ${countyName}</h3>\
                                            <p>Document lessons learned, best practices, and impact stories. Notify program managers for cross-county learning.</p>\
                                            <button onclick=\\'closeActionModal()\\' class=\\'mt-4 px-3 py-1 bg-gray-300 rounded\\'>OK</button>')">Share Success</button>`,
                                    icon: "🏅"
                                };
                            default:
                                return {msg: "", action: "", icon: ""};
                        }
                    }

                    function getColor(intensity) {
                        if (intensity === 0) return '#e5e7eb';
                        if (intensity <= minColorIntensity + 0.15 * (maxColorIntensity-minColorIntensity)) return '#86efac';
                        if (intensity <= minColorIntensity + 0.35 * (maxColorIntensity-minColorIntensity)) return '#fde68a';
                        if (intensity <= minColorIntensity + 0.7 * (maxColorIntensity-minColorIntensity)) return '#fb923c';
                        return '#dc2626';
                    }

                    const heatPoints = [];
                    const geoLayer = L.geoJSON(geojson, {
                        style: function(feature) {
                            const nameKeys = ['COUNTY', 'county', 'NAME', 'name', 'County', 'NAME_1', 'ADM1_NAME'];
                            let countyName = '';
                            for (let k of nameKeys) {
                                if (feature.properties[k]) { countyName = feature.properties[k]; break; }
                            }
                            const key = countyName.trim().toLowerCase();
                            const data = nameToCounty[key] || { intensity: 0 };
                            const centroid = turf.centroid(feature);
                            if (centroid && centroid.geometry && centroid.geometry.coordinates) {
                                const [lon, lat] = centroid.geometry.coordinates;
                                heatPoints.push([lat, lon, data.intensity]);
                            }
                            return {
                                fillColor: getColor(data.intensity),
                                weight: 2,
                                opacity: 1,
                                color: '#334155',
                                fillOpacity: 0.8
                            };
                        },
                        onEachFeature: function(feature, layer) {
                            const nameKeys = ['COUNTY', 'county', 'NAME', 'name', 'County', 'NAME_1', 'ADM1_NAME'];
                            let countyName = '';
                            for (let k of nameKeys) {
                                if (feature.properties[k]) { countyName = feature.properties[k]; break; }
                            }
                            const key = countyName.trim().toLowerCase();
                            const data = nameToCounty[key] || {
                                trainings: 0,
                                participants: 0,
                                facilities: 0,
                                intensity: 0
                            };
                            const intensityCategory = getIntensityCategory(data.intensity, minColorIntensity, maxColorIntensity);
                            const actionAdvice = getAction(intensityCategory, countyName);
                            const popupContent = `
                                <div class="p-1 min-w-48">
                                    <h4 class="font-bold text-base mb-2 text-gray-900 flex items-center gap-2">${actionAdvice.icon} ${countyName} County</h4>
                                    <div class="space-y-1 text-sm">
                                        <div class="flex justify-between"><span>Trainings:</span> <span class="font-bold">${data.trainings}</span></div>
                                        <div class="flex justify-between"><span>Participants:</span> <span class="font-bold">${data.participants}</span></div>
                                        <div class="flex justify-between"><span>Facilities:</span> <span class="font-bold">${data.facilities}</span></div>
                                        <div class="flex justify-between border-t pt-1 mt-1"><span>Intensity:</span> <span class="font-bold text-blue-700">${Number(data.intensity).toFixed(1)}</span></div>
                                        <div class="flex justify-between"><span>Intensity Category:</span> <span class="font-bold">${intensityCategory}</span></div>
                                    </div>
                                    <div class="mt-2 text-xs italic text-gray-800">${actionAdvice.msg}</div>
                                    ${actionAdvice.action}
                                    <div class="mt-2 text-[11px] text-gray-500 border-t pt-1">
                                        <b>What is intensity?</b><br>
                                        Training intensity reflects relative coverage of trainings in the county, normalized from lowest (grey) to highest (red). Higher values = broader reach.
                                    </div>
                                </div>
                            `;
                            layer.bindPopup(popupContent);
                            layer.on('mouseover', function() {
                                layer.setStyle({ weight: 3, color: '#1e293b' });
                            });
                            layer.on('mouseout', function() {
                                layer.setStyle({ weight: 2, color: '#334155' });
                            });
                        }
                    }).addTo(map);

                    const heatIntensities = heatPoints.map(pt => pt[2]);
                    const maxHeatIntensity = Math.max(...heatIntensities, 1);
                    const heatPointsNorm = heatPoints.map(pt => [pt[0], pt[1], pt[2] / maxHeatIntensity]);
                    L.heatLayer(heatPointsNorm, {
                        radius: 42,
                        blur: 25,
                        minOpacity: 0.44,
                        maxZoom: 12,
                        gradient: {
                            0.0: '#fef3c7',
                            0.2: '#fde68a',
                            0.4: '#fca311',
                            0.7: '#dc2626',
                            1.0: '#7f1d1d'
                        }
                    }).addTo(map);

                    map.fitBounds(geoLayer.getBounds(), { maxZoom: 8 });
                });

            function addWorldMask(map, kenyaGeojson) {
                let kenyaUnion = kenyaGeojson.features[0];
                for (let i = 1; i < kenyaGeojson.features.length; i++) {
                    kenyaUnion = turf.union(kenyaUnion, kenyaGeojson.features[i]);
                }
                const world = turf.polygon([[
                    [-180, -90], [180, -90], [180, 90], [-180, 90], [-180, -90]
                ]]);
                const mask = turf.difference(world, kenyaUnion);
                L.geoJSON(mask, {
                    style: {
                        fillColor: '#cbd5e1',
                        fillOpacity: 0.93,
                        stroke: false
                    },
                    interactive: false
                }).addTo(map);
                L.geoJSON(kenyaUnion, {
                    style: {
                        color: '#0f172a',
                        weight: 6,
                        fillOpacity: 0
                    },
                    interactive: false
                }).addTo(map);
            }
        });
    </script>
</body>
</html>
