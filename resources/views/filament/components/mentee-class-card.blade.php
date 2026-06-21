@php
$statusColors = [
    'active'    => ['dot' => '#16a34a', 'badge_bg' => '#dcfce7', 'badge_color' => '#15803d', 'label' => 'Active'],
    'draft'     => ['dot' => '#94a3b8', 'badge_bg' => '#f1f5f9', 'badge_color' => '#475569', 'label' => 'Draft'],
    'completed' => ['dot' => '#1d4ed8', 'badge_bg' => '#dbeafe', 'badge_color' => '#1e40af', 'label' => 'Completed'],
    'cancelled' => ['dot' => '#dc2626', 'badge_bg' => '#fef2f2', 'badge_color' => '#991b1b', 'label' => 'Cancelled'],
];
$sc = $statusColors[$enrollment['class_status']] ?? $statusColors['draft'];

$pStatusColors = [
    'enrolled'  => ['bg' => '#fffbeb', 'color' => '#92400e', 'label' => 'Enrolled'],
    'active'    => ['bg' => '#ecfdf5', 'color' => '#065f46', 'label' => 'Active'],
    'completed' => ['bg' => '#eff6ff', 'color' => '#1e40af', 'label' => 'Completed'],
];
$pc = $pStatusColors[$enrollment['p_status']] ?? $pStatusColors['enrolled'];

$progressPct = $enrollment['progress_pct'] ?? 0;
$progressColor = $progressPct >= 80 ? '#16a34a' : ($progressPct >= 50 ? '#f59e0b' : '#1d4ed8');

$modules = collect($enrollment['modules'] ?? []);
$visibleModules = $modules->take(4);
$hiddenModules  = $modules->slice(4);

$classId = $enrollment['class_id'];
@endphp

<div class="md-class-card" @if($highlight) style="border-color:#bfdbfe;box-shadow:0 0 0 1px #bfdbfe;" @endif>

    {{-- Header --}}
    <div class="md-class-header">
        <div class="md-class-status-dot" style="background:{{ $sc['dot'] }};box-shadow:0 0 0 3px {{ $sc['dot'] }}22;"></div>
        <div class="md-class-info">
            <div class="md-class-name">{{ $enrollment['class_name'] }}</div>
            <div class="md-class-meta">
                <span>{{ $enrollment['program_name'] }}</span>
                @if($enrollment['facility_name'])
                    <span>· 🏥 {{ $enrollment['facility_name'] }}</span>
                @endif
                @if($enrollment['mentor_name'] !== '—')
                    <span>· 👨‍⚕️ {{ $enrollment['mentor_name'] }}</span>
                @endif
                @if($enrollment['start_date'])
                    <span>· 📅 {{ $enrollment['start_date'] }}{{ $enrollment['end_date'] ? ' – ' . $enrollment['end_date'] : '' }}</span>
                @endif
            </div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
            <span class="md-class-badge"
                  style="background:{{ $sc['badge_bg'] }};color:{{ $sc['badge_color'] }}">
                {{ $sc['label'] }}
            </span>
            <span class="md-class-badge"
                  style="background:{{ $pc['bg'] }};color:{{ $pc['color'] }}">
                You: {{ $pc['label'] }}
            </span>
            @if($enrollment['completed_at'])
                <span style="font-size:10px;color:#64748b;">✓ {{ $enrollment['completed_at'] }}</span>
            @endif
        </div>
    </div>

    {{-- Progress bar --}}
    <div class="md-progress-row">
        <div style="font-size:12px;color:#64748b;white-space:nowrap;">
            {{ $enrollment['completed_modules'] }}/{{ $enrollment['total_modules'] }} modules
        </div>
        <div class="md-progress-bar-wrap">
            <div class="md-progress-bar-fill" style="width:{{ $progressPct }}%;background:linear-gradient(90deg,{{ $progressColor }},{{ $progressColor }}cc);"></div>
        </div>
        <div class="md-progress-pct" style="color:{{ $progressColor }}">{{ $progressPct }}%</div>
        @if($enrollment['pending_attendance'] > 0)
            <span style="background:#fef2f2;color:#dc2626;font-size:10px;font-weight:700;padding:2px 8px;border-radius:100px;white-space:nowrap;">
                🔔 {{ $enrollment['pending_attendance'] }} pending
            </span>
        @endif
        @if($enrollment['recommendations'] > 0)
            <span style="background:#ede9fe;color:#6d28d9;font-size:10px;font-weight:700;padding:2px 8px;border-radius:100px;white-space:nowrap;">
                📋 {{ $enrollment['recommendations'] }} feedback
            </span>
    @endif
    </div>
    
    {{-- Module List --}}
        @if($modules->isNotEmpty())
    <div class="md-module-list">
            @foreach($visibleModules as $mod)
                @php
                $progStatus = $mod['progress_status'];
                $modConfig  = match($progStatus) {
                    'completed'   => ['icon' => '✅', 'bg' => '#dcfce7', 'badge_bg' => '#dcfce7', 'badge_color' => '#15803d', 'label' => 'Completed'],
                    'in_progress' => ['icon' => '🔵', 'bg' => '#dbeafe', 'badge_bg' => '#dbeafe', 'badge_color' => '#1e40af', 'label' => 'In Progress'],
                    'exempted'    => ['icon' => '⭐', 'bg' => '#ede9fe', 'badge_bg' => '#ede9fe', 'badge_color' => '#6d28d9', 'label' => 'Exempted'],
                    default       => ['icon' => '⬜', 'bg' => '#f1f5f9', 'badge_bg' => '#f1f5f9', 'badge_color' => '#64748b', 'label' => 'Not Started'],
                };
            @endphp
                <div class="md-module-row">
                    <div class="md-module-icon" style="background:{{ $modConfig['bg'] }}">
                        {{ $modConfig['icon'] }}
                    </div>
                    <div class="md-module-name" style="flex:1">
                        {{ $mod['name'] }}
                    @if($mod['description'])
                            <div class="md-module-desc">{{ Str::limit($mod['description'], 70) }}</div>
                        @endif
                    @if($mod['completed_at'])
                            <div class="md-module-desc" style="color:#16a34a;">✓ Completed {{ $mod['completed_at'] }}</div>
                        @elseif($mod['started_at'])
                            <div class="md-module-desc">Started {{ $mod['started_at'] }}</div>
                        @endif
                    @if($mod['prev_class'])
                            <div class="md-module-desc" style="color:#7c3aed;">Previously completed in another class</div>
                        @endif
                    </div>

                    {{-- Status / CTA --}}
                @if($mod['attendance_open'] && !$mod['confirmed'])
                        <a href="{{ route('module.attend', ['token' => $mod['attendance_token']]) }}"
               class="md-attend-btn" target="_blank">
                ✓ Attend
                        </a>
                    @elseif($mod['confirmed'] && $progStatus !== 'completed')
                        <span class="md-module-status" style="background:#dcfce7;color:#15803d;">
                Confirmed
                        </span>
                    @else
                        <span class="md-module-status"
                  style="background:{{ $modConfig['badge_bg'] }};color:{{ $modConfig['badge_color'] }}">
                            {{ $modConfig['label'] }}
                        </span>
                    @endif

                    @if(!empty($mod['recommendation']))
                        <span title="{{ $mod['recommendation'] }}"
                  style="cursor:help;background:#ede9fe;color:#6d28d9;font-size:18px;padding:0 4px;border-radius:6px;">
                📋
                        </span>
                    @endif
                </div>
            @endforeach

            @if($hiddenModules->isNotEmpty())
                <div id="modules-{{ $classId }}" class="md-collapsible">
                    @foreach($hiddenModules as $mod)
                        @php
                        $progStatus = $mod['progress_status'];
                        $modConfig  = match($progStatus) {
                            'completed'   => ['icon' => '✅', 'bg' => '#dcfce7', 'badge_bg' => '#dcfce7', 'badge_color' => '#15803d', 'label' => 'Completed'],
                            'in_progress' => ['icon' => '🔵', 'bg' => '#dbeafe', 'badge_bg' => '#dbeafe', 'badge_color' => '#1e40af', 'label' => 'In Progress'],
                            'exempted'    => ['icon' => '⭐', 'bg' => '#ede9fe', 'badge_bg' => '#ede9fe', 'badge_color' => '#6d28d9', 'label' => 'Exempted'],
                            default       => ['icon' => '⬜', 'bg' => '#f1f5f9', 'badge_bg' => '#f1f5f9', 'badge_color' => '#64748b', 'label' => 'Not Started'],
                        };
                    @endphp
                        <div class="md-module-row">
                            <div class="md-module-icon" style="background:{{ $modConfig['bg'] }}">{{ $modConfig['icon'] }}</div>
                            <div class="md-module-name" style="flex:1">
                                {{ $mod['name'] }}
                            @if($mod['description'])
                                    <div class="md-module-desc">{{ Str::limit($mod['description'], 70) }}</div>
                                @endif
                            </div>
                        @if($mod['attendance_open'] && !$mod['confirmed'])
                                <a href="{{ route('module.attend', ['token' => $mod['attendance_token']]) }}"
                                class="md-attend-btn" target="_blank">✓ Attend</a>
                            @else
                                <span class="md-module-status"
                      style="background:{{ $modConfig['badge_bg'] }};color:{{ $modConfig['badge_color'] }}">
                                    {{ $modConfig['label'] }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <button id="toggle-{{ $classId }}" class="md-toggle-btn"
                onclick="toggleModules({{ $classId }})">
                    ▼ Show {{ $hiddenModules->count() }} more module{{ $hiddenModules->count() > 1 ? 's' : '' }}
                </button>
        @endif
    </div>
        @else
    <div style="padding:14px 22px;font-size:13px;color:#94a3b8;font-style:italic;">
        No modules have been added to this class yet.
    </div>
@endif

</div>