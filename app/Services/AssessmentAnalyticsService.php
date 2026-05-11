<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\Facility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssessmentAnalyticsService
{
    public function getSummaryStats(array $filters = []): array
    {
        $year           = $filters['year'] ?? null;
        $assessmentType = $filters['assessment_type'] ?? null;
        $countyId       = $filters['county_id'] ?? null;

        $base = fn() => Assessment::whereNull('deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessment_type', $assessmentType))
            ->when($countyId, fn($q) => $q->whereHas('facility.subcounty', fn($s) => $s->where('county_id', $countyId)));

        $skillsLabSectionId = AssessmentSection::where('code', 'skills_lab')->value('id');

        $totalAssessments   = $base()->count();
        $facilitiesAssessed = $base()->distinct('facility_id')->count('facility_id');
        $allFacilities      = Facility::whereNull('deleted_at')->count();
        $avgScore           = round((float) ($base()->where('status', 'completed')->avg('overall_percentage') ?? 0), 1);

        $withSkillsLab = (int) DB::table('assessments')
            ->join('assessment_section_scores', 'assessment_section_scores.assessment_id', '=', 'assessments.id')
            ->where('assessment_section_scores.assessment_section_id', (int) $skillsLabSectionId)
            ->where('assessment_section_scores.percentage', '>', 0)
            ->where('assessments.status', 'completed')
            ->whereNull('assessments.deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessments.assessment_type', $assessmentType))
            ->when($countyId, fn($q) => $q
                ->join('facilities', 'facilities.id', '=', 'assessments.facility_id')
                ->join('subcounties', 'subcounties.id', '=', 'facilities.subcounty_id')
                ->where('subcounties.county_id', $countyId))
            ->distinct('assessments.facility_id')
            ->count('assessments.facility_id');

        $eligible = (int) DB::table('assessments')
            ->join('assessment_section_scores', 'assessment_section_scores.assessment_id', '=', 'assessments.id')
            ->where('assessment_section_scores.assessment_section_id', (int) $skillsLabSectionId)
            ->where('assessment_section_scores.percentage', '>', 0)
            ->where('assessments.status', 'completed')
            ->where('assessments.feedback_given', true)
            ->whereNull('assessments.deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessments.assessment_type', $assessmentType))
            ->distinct('assessments.facility_id')
            ->count('assessments.facility_id');

        $facilityCoveragePercent = $allFacilities > 0
            ? round(($facilitiesAssessed / $allFacilities) * 100, 1)
            : 0;

        // YoY
        $curYear   = Carbon::now()->year;
        $prevYear  = $curYear - 1;
        $curCount  = Assessment::whereNull('deleted_at')->whereYear('assessment_date', $curYear)->count();
        $prevCount = Assessment::whereNull('deleted_at')->whereYear('assessment_date', $prevYear)->count();
        $yoyChange = $prevCount > 0
            ? round((($curCount - $prevCount) / $prevCount) * 100, 1)
            : 0;

        return compact(
            'totalAssessments', 'facilitiesAssessed', 'allFacilities',
            'avgScore', 'withSkillsLab', 'eligible',
            'facilityCoveragePercent', 'yoyChange', 'curYear'
        );
    }

    public function generateInsights(array $stats): array
    {
        $insights           = [];
        $facilitiesAssessed = $stats['facilitiesAssessed'] ?? 0;
        $allFacilities      = $stats['allFacilities'] ?? 0;
        $coverage           = $stats['facilityCoveragePercent'] ?? 0;
        $withSkillsLab      = $stats['withSkillsLab'] ?? 0;
        $eligible           = $stats['eligible'] ?? 0;
        $avgScore           = $stats['avgScore'] ?? 0;

        if ($coverage >= 60) {
            $insights[] = ['type' => 'success', 'icon' => 'check-circle', 'text' =>
                "Strong coverage at {$coverage}% — {$facilitiesAssessed} of {$allFacilities} facilities have been assessed."];
        } elseif ($coverage >= 30) {
            $insights[] = ['type' => 'warning', 'icon' => 'exclamation-triangle', 'text' =>
                "Moderate coverage at {$coverage}% — " . ($allFacilities - $facilitiesAssessed) . " facilities still need assessment."];
        } elseif ($facilitiesAssessed > 0) {
            $insights[] = ['type' => 'danger', 'icon' => 'exclamation-circle', 'text' =>
                "Low coverage at {$coverage}% — significant outreach needed; " . ($allFacilities - $facilitiesAssessed) . " facilities unassessed."];
        }

        $noSkillsLab = $facilitiesAssessed - $withSkillsLab;
        if ($noSkillsLab > 0) {
            $insights[] = ['type' => 'warning', 'icon' => 'exclamation-triangle', 'text' =>
                "{$noSkillsLab} assessed " . str('facility')->plural($noSkillsLab) . " lack a skills lab — not eligible for mentorship training."];
        }

        $pendingFeedback = $withSkillsLab - $eligible;
        if ($pendingFeedback > 0) {
            $insights[] = ['type' => 'info', 'icon' => 'clock', 'text' =>
                "{$pendingFeedback} " . str('facility')->plural($pendingFeedback) . " have a skills lab but feedback not given — partially eligible for mentorship."];
        }

        if ($avgScore > 0) {
            $grade = $avgScore >= 80 ? 'Good' : ($avgScore >= 50 ? 'Fair' : 'Needs Improvement');
            $type  = $avgScore >= 80 ? 'success' : ($avgScore >= 50 ? 'warning' : 'danger');
            $insights[] = ['type' => $type, 'icon' => 'chart-bar', 'text' =>
                "National average score is {$avgScore}% ({$grade}). {$eligible} " . str('facility')->plural($eligible) . " fully eligible for mentorship."];
        }

        return array_slice($insights, 0, 4);
    }

    public function getChartData(array $filters = []): array
    {
        $year           = $filters['year'] ?? null;
        $assessmentType = $filters['assessment_type'] ?? null;

        // ── 1. Monthly trend (12 months) grouped by type ──────────────────────
        $monthlyTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $date  = Carbon::now()->subMonths($i);
            $ms    = $date->copy()->startOfMonth();
            $me    = $date->copy()->endOfMonth();

            $counts = Assessment::whereNull('deleted_at')
                ->whereBetween('assessment_date', [$ms, $me])
                ->when($assessmentType, fn($q) => $q->where('assessment_type', $assessmentType))
                ->selectRaw('assessment_type, COUNT(*) as count')
                ->groupBy('assessment_type')
                ->pluck('count', 'assessment_type');

            $monthlyTrend[] = [
                'month'    => $date->format('M y'),
                'short'    => $date->format('M'),
                'baseline' => (int) ($counts['baseline'] ?? 0),
                'midline'  => (int) ($counts['midline']  ?? 0),
                'endline'  => (int) ($counts['endline']  ?? 0),
            ];
        }

        // ── 2. Grade distribution ─────────────────────────────────────────────
        $gradeRows = Assessment::where('status', 'completed')
            ->whereNull('deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
            ->selectRaw('overall_grade, COUNT(*) as count')
            ->groupBy('overall_grade')
            ->pluck('count', 'overall_grade');

        $gradeDistribution = [
            'green'  => (int) ($gradeRows['green']  ?? 0),
            'yellow' => (int) ($gradeRows['yellow'] ?? 0),
            'red'    => (int) ($gradeRows['red']    ?? 0),
        ];

        // ── 3. Section score averages ─────────────────────────────────────────
        $sectionAverages = DB::table('assessment_section_scores')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_section_scores.assessment_section_id')
            ->join('assessments', 'assessments.id', '=', 'assessment_section_scores.assessment_id')
            ->where('assessments.status', 'completed')
            ->whereNull('assessments.deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
            ->select(
                'assessment_sections.name',
                'assessment_sections.code',
                DB::raw('ROUND(AVG(assessment_section_scores.percentage), 1) as avg_percentage')
            )
            ->groupBy('assessment_sections.id', 'assessment_sections.name', 'assessment_sections.code')
            ->orderBy('assessment_sections.order')
            ->get()
            ->map(fn($row) => [
                'name'       => $row->name,
                'code'       => $row->code,
                'percentage' => (float) $row->avg_percentage,
                'color'      => $row->avg_percentage >= 80 ? '#10B981' : ($row->avg_percentage >= 50 ? '#F59E0B' : '#EF4444'),
            ]);

        // ── 4. Status breakdown ───────────────────────────────────────────────
        $statusRows = Assessment::whereNull('deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statusBreakdown = [
            'completed'   => (int) ($statusRows['completed']   ?? 0),
            'in_progress' => (int) ($statusRows['in_progress'] ?? 0),
            'draft'       => (int) ($statusRows['draft']       ?? 0),
        ];

        return compact('monthlyTrend', 'gradeDistribution', 'sectionAverages', 'statusBreakdown');
    }

    public function getFacilitiesReadiness(array $filters = []): Collection
    {
        $year           = $filters['year']            ?? null;
        $assessmentType = $filters['assessment_type'] ?? null;
        $countyId       = $filters['county_id']       ?? null;

        $skillsLabSectionId = (int) AssessmentSection::where('code', 'skills_lab')->value('id');

        // Subquery: latest completed assessment date per facility
        $latestDates = DB::table('assessments')
            ->select('facility_id', DB::raw('MAX(assessment_date) as max_date'))
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessment_type', $assessmentType))
            ->groupBy('facility_id');

        $assessments = Assessment::query()
            ->joinSub($latestDates, 'latest', function ($join) {
                $join->on('assessments.facility_id', '=', 'latest.facility_id')
                     ->on('assessments.assessment_date', '=', 'latest.max_date');
            })
            ->where('assessments.status', 'completed')
            ->whereNull('assessments.deleted_at')
            ->when($countyId, fn($q) => $q->whereHas(
                'facility.subcounty', fn($s) => $s->where('county_id', $countyId)
            ))
            ->with(['assessor', 'feedbackGivenBy', 'facility.facilityLevel', 'facility.subcounty.county'])
            ->addSelect([
                'assessments.*',
                DB::raw(
                    '(SELECT ss.percentage FROM assessment_section_scores ss
                      WHERE ss.assessment_id = assessments.id
                      AND ss.assessment_section_id = ' . $skillsLabSectionId . '
                      LIMIT 1) as skills_lab_percentage'
                ),
                DB::raw(
                    '(SELECT COUNT(*) FROM trainings t
                      WHERE t.facility_id = assessments.facility_id
                      AND t.type = "facility_mentorship"
                      AND t.deleted_at IS NULL) as mentorship_count'
                ),
            ])
            ->get();

        return $assessments->map(function ($assessment) {
            $hasSkillsLab  = ((float) ($assessment->skills_lab_percentage ?? 0)) > 0;
            $feedbackGiven = (bool) $assessment->feedback_given;

            $assessment->has_skills_lab      = $hasSkillsLab;
            $assessment->eligibility_status  = match (true) {
                $hasSkillsLab && $feedbackGiven => 'eligible',
                $hasSkillsLab                   => 'partial',
                default                         => 'not_eligible',
            };

            return $assessment;
        });
    }
}
