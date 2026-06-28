<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'DejaVu Sans', sans-serif;
            background: #ffffff;
        }
        .wrap {
            width: 297mm;
            height: 210mm;
            position: relative;
            overflow: hidden;
            background: #ffffff;
        }
    </style>
</head>
<body>
<div class="wrap">

    {{-- ── Decorative frame ───────────────────────────────────────────────── --}}
    <div style="position:absolute;top:4mm;left:4mm;right:4mm;bottom:4mm;border:2.5px solid #c9a227;"></div>
    <div style="position:absolute;top:7mm;left:7mm;right:7mm;bottom:7mm;border:0.75px solid rgba(201,162,39,0.32);"></div>

    {{-- ── Corner ornaments ───────────────────────────────────────────────── --}}
    <div style="position:absolute;top:1mm;left:1.8mm;font-size:14pt;color:#c9a227;font-family:'DejaVu Serif',serif;line-height:1;">&#10022;</div>
    <div style="position:absolute;top:1mm;right:1.8mm;font-size:14pt;color:#c9a227;font-family:'DejaVu Serif',serif;line-height:1;">&#10022;</div>
    <div style="position:absolute;bottom:0.5mm;left:1.8mm;font-size:14pt;color:#c9a227;font-family:'DejaVu Serif',serif;line-height:1;">&#10022;</div>
    <div style="position:absolute;bottom:0.5mm;right:1.8mm;font-size:14pt;color:#c9a227;font-family:'DejaVu Serif',serif;line-height:1;">&#10022;</div>

    {{-- ── Signature block — pinned to bottom of right panel ─────────────── --}}
    <div style="position:absolute;bottom:11mm;left:91mm;right:10mm;">
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="width:40%;vertical-align:bottom;border-top:1.5px solid #4b5563;padding-top:2.5mm;padding-right:8mm;">
                    <div style="font-size:9pt;font-weight:bold;color:#111827;">
                        {{ $participant->mentorApprovedBy?->full_name ?? ($class->training?->mentor?->full_name ?? 'Lead Mentor') }}
                    </div>
                    <div style="font-size:7.5pt;color:#6b7280;">Facility Mentor</div>
                    @if($participant->mentor_approved_at)
                    <div style="font-size:7pt;color:#9ca3af;">{{ \Carbon\Carbon::parse($participant->mentor_approved_at)->format('d M Y') }}</div>
                    @endif
                </td>
                <td style="width:20%;text-align:center;vertical-align:bottom;padding:0 3mm;">
                    <div style="width:20mm;height:20mm;border:2px solid #c9a227;border-radius:10mm;margin:0 auto 1.5mm;text-align:center;box-sizing:border-box;padding-top:5.5mm;">
                        <div style="font-size:5pt;color:#c9a227;font-weight:bold;letter-spacing:0.5px;line-height:1.7;">OFFICIAL<br>SEAL</div>
                    </div>
                </td>
                <td style="width:40%;vertical-align:bottom;border-top:1.5px solid #4b5563;padding-top:2.5mm;padding-left:8mm;">
                    <div style="font-size:9pt;font-weight:bold;color:#111827;">
                        {{ $participant->headDrmhApprovedBy?->full_name ?? 'Director, MNCH Division' }}
                    </div>
                    <div style="font-size:7.5pt;color:#6b7280;">Head DRMH, Ministry of Health</div>
                    @if($participant->head_drmh_approved_at)
                    <div style="font-size:7pt;color:#9ca3af;">{{ \Carbon\Carbon::parse($participant->head_drmh_approved_at)->format('d M Y') }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Main two-column table layout ───────────────────────────────────── --}}
    <table style="width:297mm;height:210mm;border-collapse:collapse;border-spacing:0;">
        <tr>

            {{-- ===== LEFT SIDEBAR (navy) ===== --}}
            <td style="width:70mm;background-color:#0c1445;vertical-align:top;padding:18mm 9mm 0 9mm;text-align:center;">

                {{-- Seal circle --}}
                <div style="width:44mm;height:44mm;border:2.5px solid #c9a227;border-radius:22mm;margin:0 auto 7mm;position:relative;line-height:44mm;font-family:'DejaVu Serif',serif;font-size:20pt;color:#c9a227;text-align:center;overflow:hidden;">
                    <div style="position:absolute;top:3.5mm;left:3.5mm;right:3.5mm;bottom:3.5mm;border:1px solid rgba(201,162,39,0.3);border-radius:19mm;"></div>
                    &#10022;
                </div>

                {{-- Ministry info --}}
                <div style="color:#c9a227;font-size:6.5pt;letter-spacing:1.5px;margin-bottom:2mm;font-weight:bold;">REPUBLIC OF KENYA</div>
                <div style="color:#ffffff;font-size:9pt;font-weight:bold;margin-bottom:1.5mm;">Ministry of Health</div>
                <div style="color:rgba(255,255,255,0.5);font-size:7pt;margin-bottom:9mm;">MNCH Division</div>

                <div style="height:0.5px;background-color:rgba(201,162,39,0.3);margin:0 3mm 9mm;"></div>

                {{-- Certificate number --}}
                <div style="color:rgba(255,255,255,0.38);font-size:5.5pt;letter-spacing:1px;margin-bottom:2mm;">CERTIFICATE NO.</div>
                <div style="color:#c9a227;font-size:7.5pt;font-weight:bold;margin-bottom:9mm;word-break:break-all;">
                    MNCH-{{ str_pad($participant->id, 6, '0', STR_PAD_LEFT) }}-{{ date('Y') }}
                </div>

                <div style="height:0.5px;background-color:rgba(201,162,39,0.3);margin:0 3mm 9mm;"></div>

                {{-- Date issued --}}
                <div style="color:rgba(255,255,255,0.38);font-size:5.5pt;letter-spacing:1px;margin-bottom:2mm;">DATE ISSUED</div>
                <div style="color:#ffffff;font-size:8.5pt;font-weight:bold;margin-bottom:9mm;">
                    @if($participant->head_drmh_approved_at)
                        {{ \Carbon\Carbon::parse($participant->head_drmh_approved_at)->format('d M Y') }}
                    @else
                        {{ now()->format('d M Y') }}
                    @endif
                </div>

                <div style="height:0.5px;background-color:rgba(201,162,39,0.3);margin:0 3mm 9mm;"></div>

                {{-- CPD Points --}}
                @if(isset($cpd) && $cpd['total'] > 0)
                <div style="color:rgba(255,255,255,0.38);font-size:5.5pt;letter-spacing:1px;margin-bottom:2mm;">CPD POINTS</div>
                <div style="color:#c9a227;font-size:18pt;font-weight:bold;line-height:1;margin-bottom:1.5mm;">{{ $cpd['total'] }}</div>
                <div style="color:rgba(255,255,255,0.7);font-size:6.5pt;margin-bottom:2mm;">{{ $cpd['level']['name'] ?? 'Foundation' }}</div>
                <div style="font-size:5.5pt;color:rgba(255,255,255,0.38);line-height:1.5;">
                    {{ $cpd['certificates'] ?? 0 }} cert{{ ($cpd['certificates'] ?? 0) === 1 ? '' : 's' }} &times; 3 pts<br>
                    {{ $cpd['completed_modules'] ?? 0 }} module{{ ($cpd['completed_modules'] ?? 0) === 1 ? '' : 's' }} &times; 1 pt
                </div>
                @endif

            </td>

            {{-- Gold accent stripe --}}
            <td style="width:4mm;background-color:#c9a227;padding:0;"></td>

            {{-- ===== RIGHT CONTENT ===== --}}
            <td style="vertical-align:top;padding:14mm 10mm 40mm 17mm;background-color:#ffffff;">

                {{-- Organisation tag --}}
                <div style="font-size:7pt;color:#c9a227;letter-spacing:2.5px;font-weight:bold;margin-bottom:3.5mm;">
                    MNCH &bull; MATERNAL, NEWBORN &amp; CHILD HEALTH
                </div>

                {{-- Certificate title --}}
                <div style="font-family:'DejaVu Serif',serif;font-size:24pt;font-weight:bold;color:#0c1445;line-height:1.0;margin-bottom:4mm;">
                    Certificate of Completion
                </div>

                {{-- Subtitle --}}
                <div style="font-size:7.5pt;color:#9ca3af;letter-spacing:2px;margin-bottom:4.5mm;">
                    THIS IS TO CERTIFY THAT
                </div>

                {{-- Mentee name --}}
                <div style="font-family:'DejaVu Serif',serif;font-size:21pt;font-weight:bold;color:#0c1445;border-bottom:2px solid #c9a227;padding-bottom:2.5mm;margin-bottom:3mm;line-height:1.15;">
                    {{ $participant->user?->full_name ?? trim(($participant->user?->first_name ?? '') . ' ' . ($participant->user?->last_name ?? '')) }}
                </div>

                {{-- Cadre & facility --}}
                @php
                    $infoParts = array_filter([
                        $participant->user?->cadre?->name ?? null,
                        $participant->user?->facility?->name ?? null,
                    ]);
                @endphp
                @if(count($infoParts))
                <div style="font-size:8pt;color:#6b7280;margin-bottom:4mm;">{{ implode(' · ', $infoParts) }}</div>
                @else
                <div style="margin-bottom:4mm;"></div>
                @endif

                {{-- Completion copy --}}
                <div style="font-size:9pt;color:#4b5563;margin-bottom:2mm;">has successfully completed all requirements of</div>

                {{-- Program name --}}
                <div style="font-size:13pt;font-weight:bold;color:#0c1445;margin-bottom:1.5mm;">
                    {{ $class->training?->program?->name ?? 'MNCH Mentorship Program' }}
                </div>

                {{-- Class & training facility --}}
                <div style="font-size:8.5pt;color:#374151;margin-bottom:5mm;">
                    {{ $class->name }}@if($class->training?->facility?->name) &bull; {{ $class->training->facility->name }}@endif
                </div>

                {{-- Module list --}}
                @if($modules->count() > 0)
                <div style="font-size:7.5pt;color:#1e3a5f;line-height:1.8;">
                    @foreach($modules as $i => $mod)@if($i > 0)<span style="color:#c9a227;"> &bull; </span>@endif{{ $mod->programModule?->name ?? ('Module ' . ($i + 1)) }}@endforeach
                </div>
                @endif

            </td>

        </tr>
    </table>

</div>
</body>
</html>
