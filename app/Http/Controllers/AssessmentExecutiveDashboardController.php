<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\HumanResourceResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class AssessmentExecutiveDashboardController extends Controller
{
    public function show(Assessment $assessment)
    {
        $data = $this->buildDashboardData($assessment);
        return view('analytics.assessment-executive.dashboard', $data);
    }

    public function export(Assessment $assessment)
    {
        $data = $this->buildDashboardData($assessment);
        $data['isPdf'] = true;

        $pdf = Pdf::loadView('analytics.assessment-executive.dashboard', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
                'defaultFont'          => 'sans-serif',
            ]);

        $filename = 'executive-assessment-' . $assessment->id . '-' . now()->format('Ymd') . '.pdf';
        return $pdf->download($filename);
    }

    private function buildDashboardData(Assessment $assessment): array
    {
        $assessment->load([
            'facility.facilityLevel',
            'facility.facilityType',
            'facility.subcounty.county',
        ]);

        $aId = $assessment->id;

        // ── Section scores ────────────────────────────────────────────────────
        $sectionScores = DB::table('assessment_section_scores')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_section_scores.assessment_section_id')
            ->where('assessment_section_scores.assessment_id', $aId)
            ->select(
                'assessment_sections.code',
                'assessment_sections.name',
                'assessment_section_scores.total_score',
                'assessment_section_scores.max_score',
                'assessment_section_scores.percentage',
                'assessment_section_scores.grade',
                'assessment_section_scores.total_questions',
                'assessment_section_scores.answered_questions',
            )
            ->get()
            ->keyBy('code');

        // ── Infrastructure responses ──────────────────────────────────────────
        $infraResponses = DB::table('assessment_questions')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_questions.assessment_section_id')
            ->leftJoin('assessment_question_responses', function ($j) use ($aId) {
                $j->on('assessment_question_responses.assessment_question_id', '=', 'assessment_questions.id')
                  ->where('assessment_question_responses.assessment_id', '=', $aId);
            })
            ->where('assessment_sections.code', 'infrastructure')
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get();

        // ── Skills Lab ────────────────────────────────────────────────────────
        $skillsResponses = DB::table('assessment_questions')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_questions.assessment_section_id')
            ->leftJoin('assessment_question_responses', function ($j) use ($aId) {
                $j->on('assessment_question_responses.assessment_question_id', '=', 'assessment_questions.id')
                  ->where('assessment_question_responses.assessment_id', '=', $aId);
            })
            ->where('assessment_sections.code', 'skills_lab')
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_questions.is_scored',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get();

        $skillsMaster   = $skillsResponses->firstWhere('question_code', 'SKILLS_MASTER');
        $hasDedicatedLab = $skillsMaster && $skillsMaster->response_value === 'Yes';

        $skillsAvailable = $skillsResponses->filter(fn($r) => $r->response_value === 'Yes');
        $skillsMissing   = $skillsResponses->filter(fn($r) => $r->is_scored && $r->response_value === 'No');

        // ── Information Systems ───────────────────────────────────────────────
        $infoResponses = DB::table('assessment_questions')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_questions.assessment_section_id')
            ->leftJoin('assessment_question_responses', function ($j) use ($aId) {
                $j->on('assessment_question_responses.assessment_question_id', '=', 'assessment_questions.id')
                  ->where('assessment_question_responses.assessment_id', '=', $aId);
            })
            ->where('assessment_sections.code', 'information_systems')
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_questions.is_scored',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get();

        // ── Quality of Care ───────────────────────────────────────────────────
        $qocAll = DB::table('assessment_questions')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_questions.assessment_section_id')
            ->leftJoin('assessment_question_responses', function ($j) use ($aId) {
                $j->on('assessment_question_responses.assessment_question_id', '=', 'assessment_questions.id')
                  ->where('assessment_question_responses.assessment_id', '=', $aId);
            })
            ->where('assessment_sections.code', 'quality_of_care')
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_questions.is_scored',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get()
            ->keyBy('question_code');

        // ── Human Resources ───────────────────────────────────────────────────
        $hrRows = DB::table('human_resource_responses')
            ->join('assessment_cadres', 'assessment_cadres.id', '=', 'human_resource_responses.cadre_id')
            ->where('human_resource_responses.assessment_id', $aId)
            ->select(
                'assessment_cadres.name as cadre',
                'human_resource_responses.total_in_facility',
                'human_resource_responses.etat_plus',
                'human_resource_responses.comprehensive_newborn_care',
                'human_resource_responses.imnci',
                'human_resource_responses.type_1_diabetes',
                'human_resource_responses.essential_newborn_care',
            )
            ->orderByDesc('human_resource_responses.total_in_facility')
            ->get()
            ->map(function ($r) {
                $r->total_trained = $r->etat_plus + $r->comprehensive_newborn_care + $r->imnci + $r->type_1_diabetes + $r->essential_newborn_care;
                $r->coverage_pct  = $r->total_in_facility > 0
                    ? round(min($r->total_trained / $r->total_in_facility, 1) * 100, 1)
                    : 0;

                // Percentage trained per area, relative to that cadre's total staff in facility
                $pct = fn ($count) => $r->total_in_facility > 0
                    ? round(min($count / $r->total_in_facility, 1) * 100, 1)
                    : 0;
                $r->etat_pct     = $pct($r->etat_plus);
                $r->comp_nb_pct  = $pct($r->comprehensive_newborn_care);
                $r->imnci_pct    = $pct($r->imnci);
                $r->diabetes_pct = $pct($r->type_1_diabetes);
                $r->ess_nb_pct   = $pct($r->essential_newborn_care);

                return $r;
            });

        $totalStaff   = $hrRows->sum('total_in_facility');
        $totalTrained = $hrRows->sum('total_trained');
        $hrCoverage   = $totalStaff > 0 ? round(min($totalTrained / $totalStaff, 1) * 100, 1) : 0;

        // Facility-wide % trained per area (weighted by staff count, not a simple average of cadre percentages)
        $hrAreaLabels = [
            'etat_plus'                  => 'ETAT+',
            'comprehensive_newborn_care' => 'Comprehensive Newborn Care',
            'imnci'                      => 'IMNCI',
            'type_1_diabetes'            => 'Type-1 Diabetes',
            'essential_newborn_care'     => 'Essential Newborn Care',
        ];
        $hrAreaTotals = collect($hrAreaLabels)->keys()->mapWithKeys(function ($field) use ($hrRows, $totalStaff) {
            $sum = $hrRows->sum($field);
            $pct = $totalStaff > 0 ? round(min($sum / $totalStaff, 1) * 100, 1) : 0;
            return [$field => $pct];
        });

        $hrInsights = $this->generateHrInsights($hrRows, $hrAreaTotals, $hrAreaLabels, $hrCoverage);

        // ── Health Products by department ─────────────────────────────────────
        $deptScores = DB::table('assessment_commodity_responses')
            ->join('assessment_departments', 'assessment_departments.id', '=', 'assessment_commodity_responses.assessment_department_id')
            ->where('assessment_commodity_responses.assessment_id', $aId)
            ->select(
                'assessment_departments.name as department',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(assessment_commodity_responses.available) as available'),
                DB::raw('ROUND(SUM(assessment_commodity_responses.available)/COUNT(*)*100,1) as percentage'),
            )
            ->groupBy('assessment_departments.id', 'assessment_departments.name')
            ->orderByDesc('percentage')
            ->get();

        // Health Products by category
        $categoryScores = DB::table('assessment_commodity_responses')
            ->join('commodities', 'commodities.id', '=', 'assessment_commodity_responses.commodity_id')
            ->join('commodity_categories', 'commodity_categories.id', '=', 'commodities.commodity_category_id')
            ->where('assessment_commodity_responses.assessment_id', $aId)
            ->select(
                'commodity_categories.name as category',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(assessment_commodity_responses.available) as available'),
                DB::raw('ROUND(SUM(assessment_commodity_responses.available)/COUNT(*)*100,1) as percentage'),
            )
            ->groupBy('commodity_categories.id', 'commodity_categories.name')
            ->orderByDesc('percentage')
            ->get();

        $overallCommodityPct = $deptScores->isNotEmpty()
            ? round($deptScores->avg('percentage'), 1)
            : 0;

        // ── CEO Insights ──────────────────────────────────────────────────────
        $insights = $this->generateInsights(
            $assessment, $sectionScores, $infraResponses, $hasDedicatedLab,
            $skillsMissing, $infoResponses, $qocAll, $hrRows, $deptScores,
            $hrCoverage, $overallCommodityPct
        );

        return compact(
            'assessment',
            'sectionScores',
            'infraResponses',
            'skillsResponses',
            'skillsMaster',
            'hasDedicatedLab',
            'skillsAvailable',
            'skillsMissing',
            'infoResponses',
            'qocAll',
            'hrRows',
            'totalStaff',
            'totalTrained',
            'hrCoverage',
            'hrAreaTotals',
            'hrAreaLabels',
            'hrInsights',
            'deptScores',
            'categoryScores',
            'overallCommodityPct',
            'insights',
        );
    }

    /**
     * Build HR-specific insight cards: weakest cadre, weakest training area
     * facility-wide, and cadres with zero staff trained in that weakest area.
     */
    private function generateHrInsights($hrRows, $hrAreaTotals, array $hrAreaLabels, float $hrCoverage): array
    {
        $insights = [];

        if ($hrRows->isEmpty()) {
            return $insights;
        }

        // Weakest cadre by overall coverage
        $weakestCadre = $hrRows->sortBy('coverage_pct')->first();
        if ($weakestCadre->coverage_pct < 50) {
            $type = $weakestCadre->coverage_pct < 30 ? 'danger' : 'warning';
            $insights[] = [
                'type' => $type,
                'icon' => 'user-clock',
                'text' => "{$weakestCadre->cadre} has the lowest training coverage at {$weakestCadre->coverage_pct}% — prioritise this cadre in the next mentorship cycle.",
            ];
        }

        // Weakest training area facility-wide
        $weakestAreaField = $hrAreaTotals->sort()->keys()->first();
        $weakestAreaPct   = $hrAreaTotals[$weakestAreaField];
        $weakestAreaLabel = $hrAreaLabels[$weakestAreaField];
        if ($weakestAreaPct < 60) {
            $type = $weakestAreaPct < 30 ? 'danger' : 'warning';
            $insights[] = [
                'type' => $type,
                'icon' => 'chart-pie',
                'text' => "{$weakestAreaLabel} is the weakest training area facility-wide at {$weakestAreaPct}% of staff trained — schedule a facility-wide {$weakestAreaLabel} refresher.",
            ];
        }

        // Cadres with 0% trained in the weakest area
        $zeroField = "{$weakestAreaField}_zero";
        $pctField  = match ($weakestAreaField) {
            'etat_plus'                  => 'etat_pct',
            'comprehensive_newborn_care' => 'comp_nb_pct',
            'imnci'                      => 'imnci_pct',
            'type_1_diabetes'            => 'diabetes_pct',
            'essential_newborn_care'     => 'ess_nb_pct',
            default                      => null,
        };
        if ($pctField) {
            $zeroCadres = $hrRows->filter(fn ($r) => $r->total_in_facility > 0 && $r->{$pctField} == 0)->pluck('cadre');
            if ($zeroCadres->isNotEmpty()) {
                $list = $zeroCadres->implode(', ');
                $insights[] = [
                    'type' => 'danger',
                    'icon' => 'exclamation-circle',
                    'text' => "No staff trained in {$weakestAreaLabel} for: {$list}. These cadres have zero coverage in this area.",
                ];
            }
        }

        if (empty($insights)) {
            $insights[] = [
                'type' => 'success',
                'icon' => 'check-circle',
                'text' => "Training coverage is healthy across all cadres and areas ({$hrCoverage}% overall).",
            ];
        }

        return $insights;
    }

    private function generateInsights(
        Assessment $assessment,
        $sectionScores,
        $infraResponses,
        bool $hasDedicatedLab,
        $skillsMissing,
        $infoResponses,
        $qocAll,
        $hrRows,
        $deptScores,
        float $hrCoverage,
        float $commodityPct,
    ): array {
        $insights = [];
        $overall  = (float) ($assessment->overall_percentage ?? 0);

        // Overall grade insight
        if ($overall >= 80) {
            $insights[] = ['type' => 'success', 'icon' => 'trophy', 'area' => 'Overall', 'text' => "This facility scored {$overall}% overall — a strong performance. It is well-positioned for mentorship engagement and can serve as a model site."];
        } elseif ($overall >= 50) {
            $insights[] = ['type' => 'warning', 'icon' => 'chart-line', 'area' => 'Overall', 'text' => "This facility scored {$overall}% overall — a moderate performance. Targeted support in weak areas can quickly move it to readiness."];
        } else {
            $insights[] = ['type' => 'danger', 'icon' => 'exclamation-triangle', 'area' => 'Overall', 'text' => "This facility scored {$overall}% overall — significant gaps remain. A structured improvement plan with hands-on mentorship is recommended before formal rollout."];
        }

        // Infrastructure
        $infraScore = $sectionScores->get('infrastructure');
        if ($infraScore) {
            $missingInfra = $infraResponses->filter(fn($r) => $r->response_value === 'No')->pluck('question_text');
            if ($missingInfra->isNotEmpty()) {
                $list = $missingInfra->map(fn($t) => trim(str_replace(['?','.'], '', explode('(', $t)[0])))->implode('; ');
                $insights[] = ['type' => 'warning', 'icon' => 'building', 'area' => 'Infrastructure', 'text' => "Missing infrastructure elements: {$list}. These gaps directly limit clinical capacity and should be flagged for capital planning."];
            } else {
                $insights[] = ['type' => 'success', 'icon' => 'building', 'area' => 'Infrastructure', 'text' => 'All infrastructure indicators are met. The facility has the physical environment required to deliver quality newborn and paediatric care.'];
            }
        }

        // Skills Lab
        $slScore = $sectionScores->get('skills_lab');
        if ($slScore) {
            if (!$hasDedicatedLab) {
                $insights[] = ['type' => 'danger', 'icon' => 'flask', 'area' => 'Skills Lab', 'text' => 'No dedicated skills lab exists. This limits simulation-based training capacity. Establishing even a basic skills space is the highest-impact infrastructure investment for this facility.'];
            } elseif ((float) $slScore->percentage < 60) {
                $missing = $skillsMissing->count();
                $insights[] = ['type' => 'warning', 'icon' => 'flask', 'area' => 'Skills Lab', 'text' => "A skills lab exists but {$missing} equipment/supply items are missing. Procurement of core consumables and task trainers should be prioritised before mentorship sessions."];
            } else {
                $insights[] = ['type' => 'success', 'icon' => 'flask', 'area' => 'Skills Lab', 'text' => 'Skills lab is well-equipped. This facility can host high-quality simulation-based training with minimal additional investment.'];
            }
        }

        // Human Resources
        if ($hrRows->isNotEmpty()) {
            $topCadre = $hrRows->sortByDesc('total_in_facility')->first();
            if ($hrCoverage < 30) {
                $insights[] = ['type' => 'danger', 'icon' => 'users', 'area' => 'Human Resources', 'text' => "Only {$hrCoverage}% of staff have received any specialist training. This represents a critical capacity gap requiring an urgent training plan for all cadres, prioritising {$topCadre->cadre}."];
            } elseif ($hrCoverage < 60) {
                $insights[] = ['type' => 'warning', 'icon' => 'users', 'area' => 'Human Resources', 'text' => "{$hrCoverage}% of staff have received specialist training. While progress is visible, targeted refresher courses are needed to achieve full competency across all cadres."];
            } else {
                $insights[] = ['type' => 'success', 'icon' => 'users', 'area' => 'Human Resources', 'text' => "{$hrCoverage}% staff training coverage achieved. The workforce is well-trained and can effectively absorb mentorship interventions."];
            }
        }

        // Health Products
        $lowestDept = $deptScores->sortBy('percentage')->first();
        if ($commodityPct < 50) {
            $insights[] = ['type' => 'danger', 'icon' => 'capsules', 'area' => 'Health Products', 'text' => "Commodity availability is critically low at {$commodityPct}% overall" . ($lowestDept ? " — {$lowestDept->department} is the weakest department at {$lowestDept->percentage}%" : '') . ". Emergency procurement is needed to ensure patient safety."];
        } elseif ($commodityPct < 75) {
            $insights[] = ['type' => 'warning', 'icon' => 'capsules', 'area' => 'Health Products', 'text' => "Commodity availability at {$commodityPct}% is below target" . ($lowestDept ? "; {$lowestDept->department} needs immediate restocking at {$lowestDept->percentage}%" : '') . ". A supply chain review is recommended."];
        } else {
            $insights[] = ['type' => 'success', 'icon' => 'capsules', 'area' => 'Health Products', 'text' => "Good commodity availability at {$commodityPct}%. The facility is well-stocked to deliver clinical care across departments."];
        }

        // Information Systems
        $infoScore = $sectionScores->get('information_systems');
        if ($infoScore) {
            $missingInfo = $infoResponses->filter(fn($r) => $r->is_scored && $r->response_value === 'No')->count();
            if ($missingInfo > 4) {
                $insights[] = ['type' => 'danger', 'icon' => 'database', 'area' => 'Information Systems', 'text' => "{$missingInfo} key information system elements are absent. Without complete records, data-driven decision making is compromised and M&E targets cannot be tracked."];
            } elseif ($missingInfo > 0) {
                $insights[] = ['type' => 'warning', 'icon' => 'database', 'area' => 'Information Systems', 'text' => "{$missingInfo} records or registers are missing. Completing the documentation system is needed to support reliable monitoring of newborn and paediatric outcomes."];
            } else {
                $insights[] = ['type' => 'success', 'icon' => 'database', 'area' => 'Information Systems', 'text' => 'All information system elements are in place. The facility has a strong data infrastructure to support evidence-based care and mentorship follow-up.'];
            }
        }

        // Quality of Care
        $qocScore = $sectionScores->get('quality_of_care');
        if ($qocScore) {
            $hasNeonatalAudit = ($qocAll->get('QOC_NEONATAL_AUDIT')->response_value ?? '') === 'Yes';
            $hasChildAudit    = ($qocAll->get('QOC_CHILD_AUDIT')->response_value ?? '') === 'Yes';
            $auditFreq        = $qocAll->get('QOC_AUDIT_FREQUENCY')->response_value ?? null;

            if ($hasNeonatalAudit && $hasChildAudit) {
                $freqText = $auditFreq ? " (frequency: {$auditFreq})" : '';
                $insights[] = ['type' => 'success', 'icon' => 'heartbeat', 'area' => 'Quality of Care', 'text' => "Both neonatal and child death audits are conducted{$freqText}. This is a strong quality indicator demonstrating a learning culture that can be reinforced through mentorship."];
            } elseif ($hasNeonatalAudit || $hasChildAudit) {
                $insights[] = ['type' => 'warning', 'icon' => 'heartbeat', 'area' => 'Quality of Care', 'text' => 'Partial audit practice — only one of neonatal/child audits is conducted. Completing the audit cycle for both cohorts is essential to close the mortality review loop.'];
            } else {
                $insights[] = ['type' => 'danger', 'icon' => 'heartbeat', 'area' => 'Quality of Care', 'text' => 'No death audits are being conducted. Establishing a routine mortality review process is a critical first step towards quality improvement.'];
            }
        }

        return $insights;
    }
}
