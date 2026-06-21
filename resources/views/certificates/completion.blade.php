<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Certificate of Completion</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap');

            *, *::before, *::after {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            html, body {
                width: 297mm;
                height: 210mm;
                background: #fff;
                font-family: 'Inter', 'DejaVu Sans', sans-serif;
                overflow: hidden;
            }

            .cert-page {
                width: 297mm;
                height: 210mm;
                position: relative;
                background: #fff;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 10mm;
            }

            /* ── Outer border frame ── */
            .cert-frame {
                position: absolute;
                inset: 6mm;
                border: 3px solid #1d4ed8;
                border-radius: 3mm;
            }
            .cert-frame-inner {
                position: absolute;
                inset: 9mm;
                border: 1px solid #bfdbfe;
                border-radius: 2mm;
            }

            /* ── Corner ornaments ── */
            .corner {
                position: absolute;
                width: 16mm;
                height: 16mm;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
            }
            .corner-tl {
                top: 5mm;
                left: 5mm;
            }
            .corner-tr {
                top: 5mm;
                right: 5mm;
            }
            .corner-bl {
                bottom: 5mm;
                left: 5mm;
            }
            .corner-br {
                bottom: 5mm;
                right: 5mm;
            }

            /* ── Top badge ── */
            .cert-top {
                position: relative;
                z-index: 10;
                text-align: center;
                margin-bottom: 4mm;
            }
            .cert-org {
                font-size: 9pt;
                font-weight: 600;
                color: #1d4ed8;
                letter-spacing: 0.15em;
                text-transform: uppercase;
                margin-bottom: 1mm;
            }
            .cert-program {
                font-size: 8pt;
                color: #64748b;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            /* ── Divider ── */
            .divider {
                display: flex;
                align-items: center;
                gap: 4mm;
                margin: 3mm 0;
                position: relative;
                z-index: 10;
            }
            .divider-line {
                flex: 1;
                height: 1px;
                background: linear-gradient(90deg, transparent, #bfdbfe 30%, #1d4ed8 50%, #bfdbfe 70%, transparent);
            }
            .divider-gem {
                font-size: 12pt;
                color: #1d4ed8;
            }

            /* ── Main title ── */
            .cert-of {
                font-size: 10pt;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.2em;
                text-align: center;
                position: relative;
                z-index: 10;
                margin-bottom: 1mm;
            }
            .cert-title {
                font-family: 'Playfair Display', 'DejaVu Serif', Georgia, serif;
                font-size: 36pt;
                font-weight: 700;
                color: #0f172a;
                text-align: center;
                letter-spacing: -0.01em;
                line-height: 1.1;
                position: relative;
                z-index: 10;
                margin-bottom: 4mm;
            }

            /* ── Body text ── */
            .cert-body {
                text-align: center;
                position: relative;
                z-index: 10;
                line-height: 1.8;
            }
            .cert-presented {
                font-size: 9.5pt;
                color: #64748b;
                margin-bottom: 1mm;
            }
            .cert-name {
                font-family: 'Playfair Display', 'DejaVu Serif', Georgia, serif;
                font-size: 28pt;
                font-style: italic;
                color: #1d4ed8;
                line-height: 1.2;
                margin-bottom: 2mm;
            }
            .cert-for {
                font-size: 9.5pt;
                color: #64748b;
                margin-bottom: 1mm;
            }
            .cert-class {
                font-size: 13pt;
                font-weight: 700;
                color: #0f172a;
                margin-bottom: 0.5mm;
            }
            .cert-program-name {
                font-size: 10pt;
                color: #475569;
            }

            /* ── Module list ── */
            .cert-modules {
                margin: 3mm 0;
                position: relative;
                z-index: 10;
                text-align: center;
            }
            .cert-modules-label {
                font-size: 8pt;
                color: #94a3b8;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin-bottom: 1.5mm;
            }
            .cert-modules-list {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 2mm;
            }
            .cert-module-chip {
                background: #eff6ff;
                border: 1px solid #bfdbfe;
                border-radius: 2mm;
                padding: 1mm 3mm;
                font-size: 7.5pt;
                color: #1d4ed8;
                font-weight: 500;
            }

            /* ── Signatures ── */
            .cert-signatures {
                display: flex;
                justify-content: space-around;
                width: 100%;
                margin-top: 5mm;
                position: relative;
                z-index: 10;
                padding: 0 20mm;
            }
            .sig-block {
                text-align: center;
            }
            .sig-line {
                width: 45mm;
                height: 1px;
                background: #1d4ed8;
                margin: 0 auto 1.5mm;
            }
            .sig-name {
                font-size: 8.5pt;
                font-weight: 600;
                color: #0f172a;
            }
            .sig-title {
                font-size: 7pt;
                color: #64748b;
                margin-top: 0.5mm;
            }

            /* ── Bottom metadata ── */
            .cert-meta {
                position: absolute;
                bottom: 14mm;
                left: 0;
                right: 0;
                display: flex;
                justify-content: space-between;
                padding: 0 18mm;
                font-size: 7.5pt;
                color: #94a3b8;
                z-index: 10;
            }

            /* ── Watermark ── */
            .cert-watermark {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-30deg);
                font-size: 80pt;
                font-weight: 800;
                color: rgba(29, 78, 216, 0.035);
                letter-spacing: 0.05em;
                white-space: nowrap;
                pointer-events: none;
                z-index: 1;
                font-family: 'Inter', sans-serif;
            }

            /* ── Seal ── */
            .cert-seal {
                position: absolute;
                bottom: 18mm;
                right: 20mm;
                width: 28mm;
                height: 28mm;
                background: linear-gradient(135deg, #1d4ed8, #4f46e5);
                border-radius: 50%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                color: #fff;
                z-index: 10;
                box-shadow: 0 2mm 6mm rgba(29,78,216,0.4);
            }
            .seal-icon {
                font-size: 14pt;
                margin-bottom: 0.5mm;
            }
            .seal-text {
                font-size: 5pt;
                font-weight: 700;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                text-align: center;
                line-height: 1.4;
            }
        </style>
    </head>
    <body>
    <div class="cert-page">

        {{-- Watermark --}}
        <div class="cert-watermark">MNCH</div>

        {{-- Border frames --}}
        <div class="cert-frame"></div>
        <div class="cert-frame-inner"></div>

        {{-- Corner ornaments --}}
        <div class="corner corner-tl">✦</div>
        <div class="corner corner-tr">✦</div>
        <div class="corner corner-bl">✦</div>
        <div class="corner corner-br">✦</div>

        {{-- Org header --}}
        <div class="cert-top">
            <div class="cert-org">Ministry of Health · Kenya</div>
            <div class="cert-program">Maternal, Newborn & Child Health Mentorship Platform</div>
        </div>

        {{-- Divider --}}
        <div class="divider" style="width:60%">
            <div class="divider-line"></div>
            <div class="divider-gem">✦</div>
            <div class="divider-line"></div>
        </div>

        {{-- Title --}}
        <div class="cert-of">Certificate</div>
        <div class="cert-title">of Completion</div>

        {{-- Body --}}
        <div class="cert-body">
            <div class="cert-presented">This is to certify that</div>
            <div class="cert-name">{{ $participant->user->name ?? ($participant->user->first_name . ' ' . $participant->user->last_name) }}</div>
            <div class="cert-for">has successfully completed all requirements of</div>
            <div class="cert-class">{{ $class->name }}</div>
            <div class="cert-program-name">{{ $class->training->program->name ?? 'MNCH Mentorship Program' }}</div>
        </div>

        {{-- Modules --}}
    @if($modules->count() > 0)
            <div class="cert-modules">
                <div class="cert-modules-label">Modules Completed</div>
                <div class="cert-modules-list">
                    @foreach($modules as $module)
                        <span class="cert-module-chip">{{ $module->programModule->name ?? 'Module' }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Signatures --}}
        <div class="cert-signatures">
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-name">{{ $class->training->mentor->name ?? 'Lead Mentor' }}</div>
                <div class="sig-title">Facility Mentor</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-name">Director, MNCH Division</div>
                <div class="sig-title">Ministry of Health, Kenya</div>
            </div>
        </div>

        {{-- Seal --}}
        <div class="cert-seal">
            <div class="seal-icon">🏅</div>
            <div class="seal-text">MNCH<br>Certified</div>
        </div>

        {{-- Bottom meta --}}
        <div class="cert-meta">
            <div>Facility: {{ $class->training->facility->name ?? '—' }}</div>
            <div>
                @if($class->start_date && $class->end_date)
                    Period: {{ \Carbon\Carbon::parse($class->start_date)->format('d M Y') }} – {{ \Carbon\Carbon::parse($class->end_date)->format('d M Y') }}
                @endif
            </div>
            <div>Certificate No: MNCH-{{ str_pad($class->id, 4, '0', STR_PAD_LEFT) }}-{{ str_pad($participant->id, 5, '0', STR_PAD_LEFT) }}</div>
        </div>

    </div>
</body>
</html>