<?php

namespace App\Filament\Resources\AssessmentResource\Traits;

use App\Filament\Resources\AssessmentResource;

trait HasSectionNavigation {

    /**
     * Get all sections with their completion status
     */
    protected function getAllSections(): array {
        $progress = $this->record->section_progress ?? [];

        return [
            'infrastructure' => [
                'label' => 'Infrastructure',
                'done' => $progress['infrastructure'] ?? false,
                'route' => AssessmentResource::getUrl('edit-infrastructure', ['record' => $this->record->id]),
            ],
            'skills_lab' => [
                'label' => 'Skills Lab',
                'done' => $progress['skills_lab'] ?? false,
                'route' => AssessmentResource::getUrl('edit-skills-lab', ['record' => $this->record->id]),
            ],
            'human_resources' => [
                'label' => 'Human Resources',
                'done' => $progress['human_resources'] ?? false,
                'route' => AssessmentResource::getUrl('edit-human-resources', ['record' => $this->record->id]),
            ],
            'health_products' => [
                'label' => 'Health Products',
                'done' => $progress['health_products'] ?? false,
                'route' => AssessmentResource::getUrl('edit-health-products', ['record' => $this->record->id]),
            ],
            'information_systems' => [
                'label' => 'Information Systems',
                'done' => $progress['information_systems'] ?? false,
                'route' => AssessmentResource::getUrl('edit-information-systems', ['record' => $this->record->id]),
            ],
            'quality_of_care' => [
                'label' => 'Quality of Care',
                'done' => $progress['quality_of_care'] ?? false,
                'route' => AssessmentResource::getUrl('edit-quality-of-care', ['record' => $this->record->id]),
            ],
        ];
    }

    /**
     * Get current section key - must be implemented by each page
     */
    abstract protected function getCurrentSectionKey(): string;

    /**
     * Get the route to the next incomplete section
     */
    protected function getNextSectionRoute(): string {
        $sections = $this->getAllSections();
        $currentSectionKey = $this->getCurrentSectionKey();

        // Find current section index
        $sectionKeys = array_keys($sections);
        $currentIndex = array_search($currentSectionKey, $sectionKeys);

        if ($currentIndex === false) {
            return AssessmentResource::getUrl('dashboard', ['record' => $this->record->id]);
        }

        // Look for next incomplete section
        for ($i = $currentIndex + 1; $i < count($sectionKeys); $i++) {
            $nextSection = $sections[$sectionKeys[$i]];

            if (!$nextSection['done']) {
                return $nextSection['route'];
            }
        }

        // All sections after this are complete, return to dashboard
        return AssessmentResource::getUrl('dashboard', ['record' => $this->record->id]);
    }

    /**
     * CRITICAL: Override getRedirectUrl to use our custom logic
     * This is the method Filament calls after saving
     */
    protected function getRedirectUrl(): string {
        return $this->getNextSectionRoute();
    }
}
