<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>MNCH Assessment Report - {{ $facilityInfo['name'] }}</title>
        <style>
            @page {
                margin: 20mm 15mm;
            }

            body {
                font-family: 'DejaVu Sans', sans-serif;
                font-size: 10pt;
                color: #1f2937;
                line-height: 1.4;
            }

            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 3px solid #3b82f6;
                padding-bottom: 15px;
            }

            .header h1 {
                color: #1e40af;
                font-size: 20pt;
                margin: 0 0 10px 0;
            }

            .header .subtitle {
                font-size: 12pt;
                color: #6b7280;
                margin: 0;
            }

            .info-box {
                background: #f3f4f6;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }

            .info-row {
                display: flex;
                margin-bottom: 8px;
            }

            .info-label {
                font-weight: bold;
                width: 150px;
                color: #374151;
            }

            .info-value {
                color: #1f2937;
            }

            .section-title {
                background: #3b82f6;
                color: white;
                padding: 10px 15px;
                font-size: 14pt;
                font-weight: bold;
                margin-top: 25px;
                margin-bottom: 15px;
                border-radius: 3px;
            }

            .subsection-title {
                background: #e5e7eb;
                color: #1f2937;
                padding: 8px 12px;
                font-size: 11pt;
                font-weight: bold;
                margin-top: 15px;
                margin-bottom: 10px;
                border-left: 4px solid #3b82f6;
            }

            .score-card {
                background: #f9fafb;
                border: 2px solid #e5e7eb;
                border-radius: 5px;
                padding: 15px;
                text-align: center;
                margin-bottom: 20px;
            }

            .score-large {
                font-size: 36pt;
                font-weight: bold;
                margin: 10px 0;
            }

            .score-label {
                font-size: 12pt;
                color: #6b7280;
                margin-bottom: 5px;
            }

            .grade-badge {
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                color: white;
                font-weight: bold;
                font-size: 11pt;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 9pt;
            }

            th {
                background: #3b82f6;
                color: white;
                padding: 8px;
                text-align: left;
                font-weight: bold;
            }

            td {
                padding: 8px;
                border-bottom: 1px solid #e5e7eb;
            }

            tr:nth-child(even) {
                background: #f9fafb;
            }

            .stat-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                margin-bottom: 15px;
            }

            .stat-box {
                background: #f3f4f6;
                padding: 10px;
                border-radius: 5px;
                text-align: center;
            }

            .stat-number {
                font-size: 18pt;
                font-weight: bold;
                color: #3b82f6;
            }

            .stat-label {
                font-size: 8pt;
                color: #6b7280;
                margin-top: 5px;
            }

            .badge-yes {
                background: #10b981;
                color: white;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 8pt;
                font-weight: bold;
            }

            .badge-no {
                background: #ef4444;
                color: white;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 8pt;
                font-weight: bold;
            }

            .page-break {
                page-break-after: always;
            }

            .footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 8pt;
                color: #9ca3af;
                border-top: 1px solid #e5e7eb;
                padding-top: 10px;
            }

            .key-findings {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 15px;
                margin: 15px 0;
            }

            .key-findings h3 {
                color: #92400e;
                margin: 0 0 10px 0;
                font-size: 11pt;
            }

            .key-findings ul {
                margin: 5px 0;
                padding-left: 20px;
            }

            .key-findings li {
                margin-bottom: 5px;
                color: #78350f;
            }
        </style>
    </head>
    <body>
        <!-- HEADER -->
    <div class="header">
        <h1>MNCH BASELINE ASSESSMENT REPORT</h1>
        <p class="subtitle">Maternal, Newborn & Child Health Assessment</p>
    </div>

        <!-- FACILITY INFORMATION -->
    <div class="info-box">
        <div class="info-row">
            <span class="info-label">Facility Name:</span>
            <span class="info-value">{{ $facilityInfo['name'] }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">MFL Code:</span>
            <span class="info-value">{{ $facilityInfo['mfl_code'] ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">County:</span>
            <span class="info-value">{{ $facilityInfo['county'] }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Sub-County:</span>
            <span class="info-value">{{ $facilityInfo['subcounty'] }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Level:</span>
            <span class="info-value">{{ $facilityInfo['level'] ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Assessment Type:</span>
            <span class="info-value">{{ $assessmentDetails['type'] }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Assessment Date:</span>
            <span class="info-value">{{ $assessmentDetails['date'] }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Assessor:</span>
            <span class="info-value">{{ $assessmentDetails['assessor_name'] }}</span>
        </div>
    </div>

        <!-- OVERALL SCORE -->
    <div class="score-card">
        <div class="score-label">Overall Assessment Score</div>
        <div class="score-large" style="color: {{ $overallScore['grade_color'] }}">
            {{ number_format($overallScore['percentage'], 1) }}%
        </div>
        <span class="grade-badge" style="background: {{ $overallScore['grade_color'] }}">
            {{ strtoupper($overallScore['grade']) }}
        </span>
    </div>

        <!-- SECTION SCORES SUMMARY -->
    <div class="section-title">Section Scores Overview</div>
    <table>
        <thead>
            <tr>
                <th>Section</th>
                <th style="text-align: center">Score</th>
                <th style="text-align: center">Questions</th>
                <th style="text-align: center">Percentage</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sectionScores as $section)
                <tr>
                    <td>{{ $section['section_name'] }}</td>
                    <td style="text-align: center">{{ $section['score'] }}/{{ $section['max_score'] }}</td>
                    <td style="text-align: center">{{ $section['answered_questions'] }}/{{ $section['total_questions'] }}</td>
                    <td style="text-align: center"><strong>{{ number_format($section['percentage'], 1) }}%</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="page-break"></div>

        <!-- INFRASTRUCTURE -->
    <div class="section-title">1. Infrastructure</div>

    @if($infrastructure['has_nbu'])
        <div class="subsection-title">Newborn Unit (NBU)</div>
        <table>
            <thead>
                <tr>
                    <th>NICU Beds</th>
                    <th>General Cots</th>
                    <th>KMC Beds</th>
                    <th>Total Capacity</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center"><strong>{{ $infrastructure['nbu_beds'] }}</strong></td>
                    <td style="text-align: center"><strong>{{ $infrastructure['nbu_cots'] }}</strong></td>
                    <td style="text-align: center"><strong>{{ $infrastructure['nbu_kmc'] }}</strong></td>
                    <td style="text-align: center"><strong>{{ $infrastructure['nbu_beds'] + $infrastructure['nbu_cots'] + $infrastructure['nbu_kmc'] }}</strong></td>
                </tr>
            </tbody>
        </table>
    @else
        <p><em>No Newborn Unit available at this facility.</em></p>
    @endif

    @if($infrastructure['has_paed'])
        <div class="subsection-title">Paediatric Ward</div>
        <table>
            <thead>
                <tr>
                    <th>General Ward Beds</th>
                    <th>PICU Beds</th>
                    <th>Total Capacity</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center"><strong>{{ $infrastructure['paed_beds'] }}</strong></td>
                    <td style="text-align: center"><strong>{{ $infrastructure['paed_picu'] }}</strong></td>
                    <td style="text-align: center"><strong>{{ $infrastructure['paed_beds'] + $infrastructure['paed_picu'] }}</strong></td>
                </tr>
            </tbody>
        </table>
    @else
        <p><em>No Paediatric Ward available at this facility.</em></p>
    @endif

    <div class="subsection-title">Other Infrastructure</div>
    <table>
        <thead>
            <tr>
                <th>Question</th>
                <th style="text-align: center; width: 100px">Response</th>
            </tr>
        </thead>
        <tbody>
            @foreach($infrastructure['all_responses'] as $response)
                @if(!in_array($response->question->question_code, ['INFRA_NBU', 'INFRA_PAED']))
                    <tr>
                        <td>{{ $response->question->question_text }}</td>
                        <td style="text-align: center">
                            <span class="{{ $response->response_value === 'Yes' ? 'badge-yes' : 'badge-no' }}">
                                {{ $response->response_value }}
                            </span>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

        <!-- SKILLS LAB -->
    <div class="section-title">2. Skills Lab</div>

    @if($skillsLab['has_skills_lab'])
        <p><strong>Skills Lab Status:</strong> <span class="badge-yes">AVAILABLE</span></p>

        <div class="subsection-title">Equipment & Facilities Checklist</div>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="text-align: center; width: 100px">Available</th>
                </tr>
            </thead>
            <tbody>
                @foreach($skillsLab['all_responses'] as $response)
                @if($response->question->question_code !== 'SKILLS_MASTER')
                        <tr>
                            <td>{{ $response->question->question_text }}</td>
                            <td style="text-align: center">
                                <span class="{{ $response->response_value === 'Yes' ? 'badge-yes' : 'badge-no' }}">
                                    {{ $response->response_value }}
                                </span>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @else
        <p><strong>Skills Lab Status:</strong> <span class="badge-no">NOT AVAILABLE</span></p>

        @php
        $spaceQuestion = $skillsLab['all_responses']->firstWhere('question.question_code', 'SKILLS_NO_1');
    @endphp

        @if($spaceQuestion)
            <p><strong>Room/Space for Skills Teaching:</strong> 
            <span class="{{ $spaceQuestion->response_value === 'Yes' ? 'badge-yes' : 'badge-no' }}">
                {{ $spaceQuestion->response_value }}
            </span>
        </p>
    @endif
@endif

<div class="page-break"></div>

        <!-- HUMAN RESOURCES -->
<div class="section-title">3. Human Resources</div>

<div class="subsection-title">Training Overview</div>
<table>
    <thead>
        <tr>
            <th>Total Staff</th>
            <th>ETAT+ Trained</th>
            <th>Comprehensive NB Care</th>
            <th>IMNCI Trained</th>
            <th>Type 1 Diabetes</th>
            <th>Essential NB Care</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="text-align: center"><strong>{{ $humanResources['total_staff'] }}</strong></td>
            <td style="text-align: center"><strong>{{ $humanResources['total_etat_plus'] }}</strong></td>
            <td style="text-align: center"><strong>{{ $humanResources['total_comprehensive_nb'] }}</strong></td>
            <td style="text-align: center"><strong>{{ $humanResources['total_imnci'] }}</strong></td>
            <td style="text-align: center"><strong>{{ $humanResources['total_diabetes'] }}</strong></td>
            <td style="text-align: center"><strong>{{ $humanResources['total_essential_nb'] }}</strong></td>
        </tr>
    </tbody>
</table>

<div class="subsection-title">Detailed Breakdown by Cadre</div>
<table>
    <thead>
        <tr>
            <th>Cadre</th>
            <th style="text-align: center">Total</th>
            <th style="text-align: center">ETAT+</th>
            <th style="text-align: center">Comp. NB</th>
            <th style="text-align: center">IMNCI</th>
            <th style="text-align: center">Diabetes</th>
            <th style="text-align: center">Ess. NB</th>
        </tr>
    </thead>
    <tbody>
        @foreach($humanResources['by_cadre'] as $cadre)
            @if($cadre['total'] > 0)
                <tr>
                    <td>{{ $cadre['cadre'] }}</td>
                    <td style="text-align: center"><strong>{{ $cadre['total'] }}</strong></td>
                    <td style="text-align: center">{{ $cadre['etat_plus'] }}</td>
                    <td style="text-align: center">{{ $cadre['comprehensive_nb'] }}</td>
                    <td style="text-align: center">{{ $cadre['imnci'] }}</td>
                    <td style="text-align: center">{{ $cadre['diabetes'] }}</td>
                    <td style="text-align: center">{{ $cadre['essential_nb'] }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>

<div class="page-break"></div>

        <!-- HEALTH PRODUCTS -->
<div class="section-title">4. Health Products & Commodities</div>

@foreach($healthProducts as $departmentName => $dept)
    <div class="subsection-title">{{ $departmentName }}</div>
    <p>
        <strong>Overall Availability:</strong> {{ $dept['available'] }}/{{ $dept['total'] }} 
        ({{ $dept['percentage'] }}%)
        <span class="grade-badge" style="background: {{ $dept['grade'] === 'green' ? '#10b981' : ($dept['grade'] === 'yellow' ? '#f59e0b' : '#ef4444') }}">
            {{ strtoupper($dept['grade']) }}
        </span>
    </p>

    <table>
        <thead>
            <tr>
                <th>Commodity</th>
                <th>Category</th>
                <th style="text-align: center; width: 100px">Available</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dept['commodities'] as $commodity)
                <tr>
                    <td>{{ $commodity['name'] }}</td>
                    <td>{{ $commodity['category'] }}</td>
                    <td style="text-align: center">
                        <span class="{{ $commodity['available'] ? 'badge-yes' : 'badge-no' }}">
                            {{ $commodity['available'] ? 'Yes' : 'No' }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-bottom: 15px;">
        <strong>Summary by Category:</strong>
        @foreach($dept['by_category'] as $category)
            <div style="display: inline-block; margin-right: 15px; margin-top: 5px;">
                <span style="font-weight: bold;">{{ $category['name'] }}:</span> 
                {{ $category['available'] }}/{{ $category['total'] }} ({{ $category['percentage'] }}%)
            </div>
        @endforeach
    </div>

    @if(!$loop->last)
        <div style="margin-bottom: 20px;"></div>
    @endif
@endforeach

        <!-- INFORMATION SYSTEMS -->
<div class="section-title">5. Information Systems</div>
<table>
    <thead>
        <tr>
            <th>Question</th>
            <th style="text-align: center; width: 80px">Response</th>
        </tr>
    </thead>
    <tbody>
        @foreach($informationSystems['all_responses'] as $response)
            <tr>
                <td>{{ $response->question->question_text }}</td>
                <td style="text-align: center">
                    <span class="{{ $response->response_value === 'Yes' ? 'badge-yes' : 'badge-no' }}">
                        {{ $response->response_value }}
                    </span>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="page-break"></div>

        <!-- QUALITY OF CARE -->
<div class="section-title">6. Quality of Care</div>

@if($qualityOfCare['yes_no']->count() > 0 || $qualityOfCare['select']->count() > 0)
    <div class="subsection-title">Audit & Process Compliance</div>
    <table>
        <thead>
            <tr>
                <th>Question</th>
                <th style="text-align: center; width: 120px">Response</th>
            </tr>
        </thead>
        <tbody>
            @foreach($qualityOfCare['yes_no'] as $response)
                <tr>
                    <td>{{ $response->question->question_text }}</td>
                    <td style="text-align: center">
                        <span class="{{ $response->response_value === 'Yes' ? 'badge-yes' : 'badge-no' }}">
                            {{ $response->response_value }}
                        </span>
                    </td>
                </tr>
            @endforeach

            @foreach($qualityOfCare['select'] as $response)
                <tr>
                    <td>{{ $response->question->question_text }}</td>
                    <td style="text-align: center">
                        <strong>{{ $response->response_value }}</strong>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($qualityOfCare['newborn_stats']->count() > 0)
    <div class="subsection-title">Newborn Care Statistics (September 2025)</div>
    <table>
        <thead>
            <tr>
                <th>Indicator</th>
                <th style="text-align: center; width: 100px">Count</th>
            </tr>
        </thead>
        <tbody>
            @foreach($qualityOfCare['newborn_stats'] as $response)
                <tr>
                    <td>{{ $response->question->question_text }}</td>
                    <td style="text-align: center"><strong>{{ $response->response_value ?? 0 }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($qualityOfCare['paed_stats']->count() > 0)
    <div class="subsection-title">Paediatric Care Statistics (September 2025)</div>
    <table>
        <thead>
            <tr>
                <th>Indicator</th>
                <th style="text-align: center; width: 100px">Count</th>
            </tr>
        </thead>
        <tbody>
            @foreach($qualityOfCare['paed_stats'] as $response)
                <tr>
                    <td>{{ $response->question->question_text }}</td>
                    <td style="text-align: center"><strong>{{ $response->response_value ?? 0 }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

        <!-- KEY FINDINGS -->
<div class="key-findings">
    <h3>📊 Key Findings & Recommendations</h3>
    <ul>
        @if($overallScore['percentage'] >= 80)
            <li>Facility demonstrates strong MNCH service capacity with {{ number_format($overallScore['percentage'], 1) }}% overall score</li>
        @elseif($overallScore['percentage'] >= 50)
            <li>Facility shows moderate MNCH capacity ({{ number_format($overallScore['percentage'], 1) }}%). Focus on identified gaps for improvement</li>
        @else
            <li>Significant gaps identified in MNCH services ({{ number_format($overallScore['percentage'], 1) }}%). Urgent interventions required</li>
        @endif

        @if($humanResources['total_staff'] > 0)
            <li>Total staff: {{ $humanResources['total_staff'] }}. Training coverage: ETAT+ ({{ $humanResources['total_etat_plus'] }}), IMNCI ({{ $humanResources['total_imnci'] }})</li>
        @endif

        @if(!$skillsLab['has_skills_lab'])
            <li>⚠ Skills lab not available - consider establishing training facility</li>
        @endif

        @if(!$infrastructure['has_nbu'])
            <li>⚠ No dedicated newborn unit - critical for MNCH service delivery</li>
        @endif
    </ul>
</div>

        <!-- FOOTER -->
<div class="footer">
    <p>Generated on {{ now()->format('F j, Y \a\t g:i A') }} | MNCH Assessment System | Page <span class="pageNumber"></span></p>
</div>
</body>
</html>