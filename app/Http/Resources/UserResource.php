<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource {

    public function toArray($request): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->getRoleNames()->first() ?? 'user',
            'roles' => $this->getRoleNames()->values(),
            'status' => $this->status ?? 'active',
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'initials' => $this->getInitials(),
            'facility' => $this->whenLoaded('facility', fn() => [
                'id' => $this->facility->id,
                'name' => $this->facility->name,
                'mfl_code' => $this->facility->mfl_code,
                'county' => $this->facility->subcounty->county->name ?? null,
                'subcounty' => $this->facility->subcounty->name ?? null,
                    ]),
            'cadre' => $this->whenLoaded('cadre', fn() => $this->cadre?->name),
            'department' => $this->whenLoaded('department', fn() => $this->department?->name),
            'created_at' => $this->created_at?->toDateString(),
        ];
    }

    private function getInitials(): string {
        $parts = explode(' ', $this->name ?? '');
        return strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
    }
}
