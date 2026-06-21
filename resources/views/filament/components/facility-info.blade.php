<div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 space-y-2">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400">MFL Code</span>
            <p class="font-semibold">{{ $info['mfl_code'] ?? 'N/A' }}</p>
        </div>
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400">Level</span>
            <p class="font-semibold">{{ $info['level'] ?? 'N/A' }}</p>
        </div>
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400">Ownership</span>
            <p class="font-semibold">{{ $info['ownership'] ?? 'N/A' }}</p>
        </div>
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400">County</span>
            <p class="font-semibold">{{ $info['county'] ?? 'N/A' }}</p>
        </div>
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400">Sub-County</span>
            <p class="font-semibold">{{ $info['subcounty'] ?? 'N/A' }}</p>
        </div>
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400">Contact</span>
            <p class="font-semibold">{{ $info['contact'] ?? 'N/A' }}</p>
        </div>
    </div>
</div>