<x-filament-panels::page>
    <style>
        .rag-chat-shell {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            height: clamp(560px, calc(100vh - 11rem), 760px);
            min-height: 0;
            overflow: hidden;
            border: 1px solid rgb(229 231 235);
            border-radius: 8px;
            background: rgb(255 255 255);
        }

        .dark .rag-chat-shell {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .rag-chat-sidebar {
            display: flex;
            min-height: 0;
            flex-direction: column;
            border-right: 1px solid rgb(229 231 235);
            background: rgb(249 250 251);
        }

        .dark .rag-chat-sidebar {
            border-color: rgb(55 65 81);
            background: rgb(3 7 18);
        }

        .rag-chat-sidebar-header,
        .rag-chat-header,
        .rag-chat-composer {
            padding: 16px;
        }

        .rag-chat-header,
        .rag-chat-composer {
            border-color: rgb(229 231 235);
        }

        .dark .rag-chat-header,
        .dark .rag-chat-composer {
            border-color: rgb(55 65 81);
        }

        .rag-chat-header {
            border-bottom-width: 1px;
        }

        .rag-chat-composer {
            border-top-width: 1px;
            background: rgb(255 255 255);
        }

        .dark .rag-chat-composer {
            background: rgb(17 24 39);
        }

        .rag-chat-main {
            display: flex;
            min-width: 0;
            min-height: 0;
            flex-direction: column;
        }

        .rag-chat-list {
            min-height: 0;
            flex: 1;
            overflow-y: auto;
            padding: 0 10px 14px;
        }

        .rag-chat-thread {
            min-height: 0;
            flex: 1;
            overflow-y: auto;
            padding: 22px;
            background:
                linear-gradient(rgb(255 255 255), rgb(255 255 255)) padding-box,
                linear-gradient(180deg, rgba(20, 184, 166, .08), rgba(59, 130, 246, .04)) border-box;
        }

        .dark .rag-chat-thread {
            background: rgb(17 24 39);
        }

        .rag-chat-row {
            display: flex;
            margin-bottom: 18px;
            animation: rag-message-fade-in .22s ease-out both;
        }

        .rag-chat-row-user {
            justify-content: flex-end;
        }

        .rag-chat-message {
            max-width: min(760px, 88%);
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
            line-height: 1.65;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
        }

        .rag-chat-message-user {
            background: linear-gradient(135deg, rgb(37 99 235), rgb(14 116 144));
            color: white;
        }

        .rag-chat-message-assistant {
            border: 1px solid rgb(229 231 235);
            background: rgb(249 250 251);
            color: rgb(17 24 39);
        }

        .dark .rag-chat-message-assistant {
            border-color: rgb(55 65 81);
            background: rgb(31 41 55);
            color: rgb(243 244 246);
        }

        .rag-chat-role {
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            opacity: .72;
        }

        .rag-chat-text {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .rag-chat-markdown {
            overflow-wrap: anywhere;
        }

        .rag-chat-markdown > :first-child {
            margin-top: 0;
        }

        .rag-chat-markdown > :last-child {
            margin-bottom: 0;
        }

        .rag-chat-markdown p,
        .rag-chat-markdown ul,
        .rag-chat-markdown ol,
        .rag-chat-markdown blockquote,
        .rag-chat-markdown pre,
        .rag-chat-markdown table {
            margin: 0 0 11px;
        }

        .rag-chat-markdown ul,
        .rag-chat-markdown ol {
            padding-left: 22px;
        }

        .rag-chat-markdown ul {
            list-style: disc;
        }

        .rag-chat-markdown ol {
            list-style: decimal;
        }

        .rag-chat-markdown li + li {
            margin-top: 4px;
        }

        .rag-chat-markdown strong {
            font-weight: 700;
            color: rgb(15 23 42);
        }

        .dark .rag-chat-markdown strong {
            color: rgb(255 255 255);
        }

        .rag-chat-markdown a {
            color: rgb(37 99 235);
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .rag-chat-markdown code {
            border-radius: 5px;
            background: rgb(226 232 240);
            padding: 2px 5px;
            font-size: 12px;
            color: rgb(15 23 42);
        }

        .dark .rag-chat-markdown code {
            background: rgb(15 23 42);
            color: rgb(226 232 240);
        }

        .rag-chat-markdown pre {
            overflow-x: auto;
            border-radius: 8px;
            background: rgb(15 23 42);
            padding: 12px;
            color: rgb(226 232 240);
        }

        .rag-chat-markdown pre code {
            background: transparent;
            padding: 0;
            color: inherit;
        }

        .rag-chat-markdown blockquote {
            border-left: 3px solid rgb(20 184 166);
            padding-left: 12px;
            color: rgb(71 85 105);
        }

        .dark .rag-chat-markdown blockquote {
            color: rgb(203 213 225);
        }

        .rag-chat-markdown table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .rag-chat-markdown th,
        .rag-chat-markdown td {
            border: 1px solid rgb(203 213 225);
            padding: 7px 9px;
            text-align: left;
            vertical-align: top;
        }

        .dark .rag-chat-markdown th,
        .dark .rag-chat-markdown td {
            border-color: rgb(71 85 105);
        }

        .rag-chat-inline-citation {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            margin: 0 2px;
            border: 1px solid rgb(14 165 233);
            border-radius: 999px;
            background: rgb(240 249 255);
            color: rgb(3 105 161);
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            vertical-align: baseline;
        }

        .dark .rag-chat-inline-citation {
            border-color: rgb(56 189 248);
            background: rgba(8, 47, 73, .65);
            color: rgb(186 230 253);
        }

        .rag-chat-citations {
            margin-top: 14px;
            border-top: 1px solid rgb(226 232 240);
            padding-top: 12px;
        }

        .dark .rag-chat-citations {
            border-color: rgb(55 65 81);
        }

        .rag-chat-citations-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: rgb(71 85 105);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .dark .rag-chat-citations-title {
            color: rgb(203 213 225);
        }

        .rag-chat-citation-grid {
            display: grid;
            gap: 8px;
        }

        .rag-chat-input-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
        }

        .rag-chat-textarea {
            width: 100%;
            border: 1px solid rgb(209 213 219);
            border-radius: 8px;
            background: white;
            color: rgb(17 24 39);
            font-size: 14px;
            line-height: 22px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
            min-height: 48px;
            max-height: 154px;
            overflow-y: auto;
            resize: none;
            padding: 12px 14px;
        }

        .dark .rag-chat-textarea {
            border-color: rgb(75 85 99);
            background: rgb(3 7 18);
            color: rgb(243 244 246);
        }

        .rag-chat-textarea:disabled {
            cursor: not-allowed;
            opacity: .62;
        }

        .rag-chat-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 12px;
            font-weight: 600;
        }

        .rag-chat-chip-ok {
            background: rgb(220 252 231);
            color: rgb(22 101 52);
        }

        .rag-chat-chip-down {
            background: rgb(254 226 226);
            color: rgb(153 27 27);
        }

        .rag-chat-conversation-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 30px;
            gap: 6px;
            align-items: center;
            margin-bottom: 4px;
            border-radius: 8px;
            padding: 4px;
            color: rgb(55 65 81);
            transition: background-color .16s ease, color .16s ease, box-shadow .16s ease;
        }

        .rag-chat-conversation-item:hover {
            background: rgb(255 255 255);
        }

        .dark .rag-chat-conversation-item {
            color: rgb(229 231 235);
        }

        .dark .rag-chat-conversation-item:hover {
            background: rgba(255, 255, 255, .08);
        }

        .rag-chat-conversation-item-active {
            background: rgb(37 99 235);
            color: white;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .08);
        }

        .rag-chat-conversation-item-active:hover {
            background: rgb(37 99 235);
        }

        .rag-chat-conversation-select {
            min-width: 0;
            border-radius: 6px;
            padding: 7px 8px;
            text-align: left;
        }

        .rag-chat-conversation-delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            color: rgb(100 116 139);
            opacity: .72;
            transition: background-color .16s ease, color .16s ease, opacity .16s ease;
        }

        .rag-chat-conversation-delete:hover,
        .rag-chat-conversation-delete:focus-visible {
            background: rgb(254 226 226);
            color: rgb(185 28 28);
            opacity: 1;
            outline: none;
        }

        .rag-chat-conversation-item-active .rag-chat-conversation-delete {
            color: rgb(219 234 254);
        }

        .rag-chat-conversation-item-active .rag-chat-conversation-delete:hover,
        .rag-chat-conversation-item-active .rag-chat-conversation-delete:focus-visible {
            background: rgba(255, 255, 255, .16);
            color: white;
        }

        .rag-chat-citation {
            border: 1px solid rgb(229 231 235);
            border-radius: 8px;
            background: rgb(255 255 255);
            color: rgb(55 65 81);
            font-size: 12px;
            overflow: hidden;
        }

        .dark .rag-chat-citation {
            border-color: rgb(75 85 99);
            background: rgb(17 24 39);
            color: rgb(209 213 219);
        }

        .rag-chat-citation summary {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 9px;
            align-items: center;
            padding: 9px 10px;
            cursor: pointer;
            list-style: none;
        }

        .rag-chat-citation summary::-webkit-details-marker {
            display: none;
        }

        .rag-chat-citation-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 999px;
            background: rgb(14 165 233);
            color: white;
            font-size: 11px;
            font-weight: 800;
        }

        .rag-chat-citation-heading {
            min-width: 0;
        }

        .rag-chat-citation-document {
            display: block;
            overflow: hidden;
            color: rgb(15 23 42);
            font-weight: 700;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dark .rag-chat-citation-document {
            color: rgb(248 250 252);
        }

        .rag-chat-citation-locator {
            display: block;
            margin-top: 1px;
            color: rgb(100 116 139);
            font-size: 11px;
            font-weight: 600;
        }

        .dark .rag-chat-citation-locator {
            color: rgb(148 163 184);
        }

        .rag-chat-citation-toggle {
            color: rgb(100 116 139);
            font-size: 11px;
            font-weight: 700;
        }

        .rag-chat-citation-content {
            border-top: 1px solid rgb(226 232 240);
            padding: 10px;
            color: rgb(71 85 105);
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .dark .rag-chat-citation-content {
            border-color: rgb(55 65 81);
            color: rgb(203 213 225);
        }

        .rag-chat-citation-content > :first-child {
            margin-top: 0;
        }

        .rag-chat-citation-content > :last-child {
            margin-bottom: 0;
        }

        .rag-chat-citation-content p,
        .rag-chat-citation-content ul,
        .rag-chat-citation-content ol {
            margin: 0 0 8px;
        }

        .rag-chat-citation-content ul,
        .rag-chat-citation-content ol {
            padding-left: 20px;
        }

        .rag-chat-citation-content ul {
            list-style: disc;
        }

        .rag-chat-citation-content ol {
            list-style: decimal;
        }

        .rag-chat-citation-content li {
            padding-left: 2px;
        }

        .rag-chat-citation-content li + li {
            margin-top: 3px;
        }

        .rag-chat-citation-content strong {
            display: inline-block;
            margin-bottom: 2px;
            color: rgb(15 23 42);
            font-weight: 800;
        }

        .dark .rag-chat-citation-content strong {
            color: rgb(248 250 252);
        }

        .rag-chat-source-media {
            border-top: 1px solid rgb(226 232 240);
            padding: 10px;
        }

        .dark .rag-chat-source-media {
            border-color: rgb(55 65 81);
        }

        .rag-chat-source-media-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            color: rgb(71 85 105);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .dark .rag-chat-source-media-header {
            color: rgb(203 213 225);
        }

        .rag-chat-source-media-grid {
            display: grid;
            gap: 8px;
        }

        .rag-chat-source-media-count-1 .rag-chat-source-media-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .rag-chat-source-media-count-2 .rag-chat-source-media-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .rag-chat-source-media-count-3 .rag-chat-source-media-grid {
            grid-template-columns: minmax(0, 1.25fr) minmax(0, .75fr);
        }

        .rag-chat-source-media-count-3 .rag-chat-source-media-item:first-child {
            grid-row: span 2;
        }

        .rag-chat-source-media-count-many .rag-chat-source-media-grid {
            grid-template-columns: repeat(auto-fit, minmax(132px, 1fr));
        }

        .rag-chat-source-media-item {
            min-width: 0;
            margin: 0;
        }

        .rag-chat-source-media-link {
            display: flex;
            min-height: 0;
            overflow: hidden;
            border-radius: 8px;
            background: rgb(248 250 252);
        }

        .dark .rag-chat-source-media-link {
            background: rgb(15 23 42);
        }

        .rag-chat-source-image {
            display: block;
            width: 100%;
            border: 1px solid rgb(203 213 225);
            border-radius: 8px;
            background: rgb(248 250 252);
            object-fit: contain;
        }

        .rag-chat-source-media-count-1 .rag-chat-source-image {
            max-height: 460px;
        }

        .rag-chat-source-media-count-2 .rag-chat-source-image,
        .rag-chat-source-media-count-3 .rag-chat-source-image,
        .rag-chat-source-media-count-many .rag-chat-source-image {
            aspect-ratio: 4 / 3;
            height: 100%;
            max-height: 260px;
        }

        .rag-chat-source-media-count-3 .rag-chat-source-media-item:first-child .rag-chat-source-image {
            max-height: 528px;
        }

        .dark .rag-chat-source-image {
            border-color: rgb(75 85 99);
            background: rgb(15 23 42);
        }

        .rag-chat-source-media-caption {
            margin-top: 4px;
            overflow: hidden;
            color: rgb(100 116 139);
            font-size: 11px;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dark .rag-chat-source-media-caption {
            color: rgb(148 163 184);
        }

        .rag-thinking {
            align-items: center;
            display: inline-flex;
            gap: 3px;
        }

        .rag-thinking-dot {
            width: 5px;
            height: 5px;
            border-radius: 999px;
            background: rgb(20 184 166);
            animation: rag-thinking-bounce 1s infinite ease-in-out both;
        }

        .rag-thinking-dot:nth-child(2) {
            animation-delay: .14s;
        }

        .rag-thinking-dot:nth-child(3) {
            animation-delay: .28s;
        }

        @keyframes rag-message-fade-in {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes rag-thinking-bounce {
            0%,
            80%,
            100% {
                opacity: .35;
                transform: translateY(0);
            }
            40% {
                opacity: 1;
                transform: translateY(-4px);
            }
        }

        @media (max-width: 900px) {
            .rag-chat-shell {
                grid-template-columns: 1fr;
                height: clamp(620px, calc(100vh - 8rem), 820px);
            }

            .rag-chat-sidebar {
                max-height: 230px;
                border-right: 0;
                border-bottom: 1px solid rgb(229 231 235);
            }

            .dark .rag-chat-sidebar {
                border-color: rgb(55 65 81);
            }

            .rag-chat-input-row {
                grid-template-columns: 1fr;
            }

            .rag-chat-thread {
                min-height: 0;
                padding: 14px;
            }

            .rag-chat-source-media-count-2 .rag-chat-source-media-grid,
            .rag-chat-source-media-count-3 .rag-chat-source-media-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .rag-chat-source-media-count-3 .rag-chat-source-media-item:first-child {
                grid-row: auto;
            }

            .rag-chat-source-media-count-2 .rag-chat-source-image,
            .rag-chat-source-media-count-3 .rag-chat-source-image,
            .rag-chat-source-media-count-many .rag-chat-source-image {
                max-height: 320px;
            }
        }
    </style>

    <div
        class="rag-chat-shell"
        x-data="{
            scrollThread() {
                const el = document.getElementById('rag-thread');
                this.$el.scrollIntoView({ block: 'end', behavior: 'smooth' });
                if (el) el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
            },
            scheduleScroll() {
                this.$nextTick(() => this.scrollThread());
                setTimeout(() => this.scrollThread(), 80);
                setTimeout(() => this.scrollThread(), 220);
            },
        }"
        x-init="$nextTick(() => scrollThread())"
        x-on:rag-message-added.window="scheduleScroll()"
    >
        <aside class="rag-chat-sidebar">
            <div class="rag-chat-sidebar-header">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Chats</h2>
                    <x-filament::button size="sm" icon="heroicon-o-plus" wire:click="newChat">
                        New
                    </x-filament::button>
                </div>

                <span class="rag-chat-chip {{ ($health['ok'] ?? false) ? 'rag-chat-chip-ok' : 'rag-chat-chip-down' }}">
                    {{ ($health['ok'] ?? false) ? 'Service online' : 'Service unavailable' }}
                </span>
            </div>

            <div class="rag-chat-list">
                @forelse ($this->conversations as $conversation)
                    <div class="rag-chat-conversation-item {{ $conversationId === $conversation->id ? 'rag-chat-conversation-item-active' : '' }}">
                        <button
                            type="button"
                            wire:click="selectConversation({{ $conversation->id }})"
                            class="rag-chat-conversation-select"
                        >
                            <span class="block truncate text-sm font-medium">{{ $conversation->title ?: 'Untitled chat' }}</span>
                            <span class="block truncate text-xs {{ $conversationId === $conversation->id ? 'text-primary-100' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ optional($conversation->last_message_at)->diffForHumans() ?: 'Just now' }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="rag-chat-conversation-delete"
                            wire:click="deleteConversation({{ $conversation->id }})"
                            wire:confirm="Delete this chat?"
                            aria-label="Delete {{ $conversation->title ?: 'chat' }}"
                            title="Delete chat"
                        >
                            <x-heroicon-o-trash class="h-4 w-4" />
                        </button>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 p-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        No chats yet
                    </div>
                @endforelse
            </div>
        </aside>

        <section class="rag-chat-main">
            <header class="rag-chat-header">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-base font-semibold text-gray-950 dark:text-white">MNCHGPT</h1>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            {{ $conversationId ? 'Conversation ready' : 'Start a new conversation' }}
                        </p>
                    </div>

                    @if ($conversationId)
                        <x-filament::button
                            color="danger"
                            size="sm"
                            icon="heroicon-o-trash"
                            wire:click="deleteConversation({{ $conversationId }})"
                            wire:confirm="Delete this chat?"
                        >
                            Delete
                        </x-filament::button>
                    @endif
                </div>
            </header>

            <div id="rag-thread" class="rag-chat-thread">
                @forelse ($this->messages as $message)
                    <div class="rag-chat-row {{ $message->role === 'user' ? 'rag-chat-row-user' : '' }}">
                        <article class="rag-chat-message {{ $message->role === 'user' ? 'rag-chat-message-user' : 'rag-chat-message-assistant' }}">
                            <div class="rag-chat-role">{{ $message->role === 'user' ? 'Question' : 'Answer' }}</div>

                            @if ($message->role === 'assistant')
                                <div class="rag-chat-markdown">
                                    @php
                                        $renderedAnswer = Str::markdown(e($message->content));
                                        $renderedAnswer = preg_replace('/\[(\d{1,2})\]/', '<span class="rag-chat-inline-citation" title="Source $1">[$1]</span>', $renderedAnswer);
                                    @endphp

                                    {!! $renderedAnswer !!}
                                </div>
                            @else
                                <div class="rag-chat-text">{{ $message->content }}</div>
                            @endif

                            @if ($message->error_message)
                                <div class="mt-3 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700 dark:border-danger-900 dark:bg-danger-950/30 dark:text-danger-300">
                                    {{ $message->error_message }}
                                </div>
                            @endif

                            @if (! empty($message->citations))
                                <section class="rag-chat-citations" aria-label="Sources">
                                    <div class="rag-chat-citations-title">
                                        Sources
                                        <span>{{ count($message->citations) }}</span>
                                    </div>

                                    <div class="rag-chat-citation-grid">
                                        @foreach ($message->citations as $source)
                                            @php
                                                $locator = null;
                                                if (! empty($source['page'])) {
                                                    $locator = 'Page '.$source['page'];
                                                } elseif (! empty($source['slide'])) {
                                                    $locator = 'Slide '.$source['slide'];
                                                } elseif (! empty($source['locator_type']) && ! empty($source['locator'])) {
                                                    $locator = Str::headline($source['locator_type']).' '.$source['locator'];
                                                }

                                                $excerpt = $source['content'] ?? $source['excerpt'] ?? $source['text'] ?? null;
                                                $mediaItems = collect($source['media'] ?? [])->filter(fn ($media) => ! empty($media['url']))->values();
                                                $mediaCount = $mediaItems->count();
                                                $mediaLayout = match (true) {
                                                    $mediaCount === 1 => 'rag-chat-source-media-count-1',
                                                    $mediaCount === 2 => 'rag-chat-source-media-count-2',
                                                    $mediaCount === 3 => 'rag-chat-source-media-count-3',
                                                    $mediaCount > 3 => 'rag-chat-source-media-count-many',
                                                    default => '',
                                                };
                                            @endphp

                                            <details class="rag-chat-citation" @if ($loop->first) open @endif>
                                                <summary>
                                                    <span class="rag-chat-citation-number">[{{ $loop->iteration }}]</span>

                                                    <span class="rag-chat-citation-heading">
                                                        <span class="rag-chat-citation-document">
                                                            {{ $source['document'] ?? $source['title'] ?? $source['source'] ?? 'Document' }}
                                                        </span>

                                                        @if ($locator)
                                                            <span class="rag-chat-citation-locator">{{ $locator }}</span>
                                                        @endif
                                                    </span>

                                                    <span class="rag-chat-citation-toggle">View</span>
                                            </summary>

                                                @if (filled($excerpt))
                                                    <div class="rag-chat-citation-content">
                                                        {!! \App\Support\RagSourceFormatter::html($excerpt) !!}
                                                    </div>
                                                @endif

                                                @if ($mediaCount > 0)
                                                    <div class="rag-chat-source-media {{ $mediaLayout }}">
                                                        <div class="rag-chat-source-media-header">
                                                            <span>{{ $mediaCount === 1 ? 'Slide visual' : 'Slide visuals' }}</span>
                                                            <span>{{ $mediaCount }}</span>
                                                        </div>

                                                        <div class="rag-chat-source-media-grid">
                                                            @foreach ($mediaItems as $media)
                                                                <figure class="rag-chat-source-media-item">
                                                                    <a
                                                                        class="rag-chat-source-media-link"
                                                                        href="{{ $media['url'] }}"
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                    >
                                                                        <img
                                                                            class="rag-chat-source-image"
                                                                            src="{{ $media['url'] }}"
                                                                            alt="{{ $media['alt'] ?? 'Slide image' }}"
                                                                            loading="lazy"
                                                                        >
                                                                    </a>

                                                                    @if (! empty($media['alt']) && $mediaCount > 1)
                                                                        <figcaption class="rag-chat-source-media-caption">
                                                                            {{ $media['alt'] }}
                                                                        </figcaption>
                                                                    @endif
                                                                </figure>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </details>
                                        @endforeach
                                    </div>
                                </section>
                            @endif
                        </article>
                    </div>
                @empty
                    <div class="flex h-full items-center justify-center">
                        <div class="max-w-xl text-center">
                            <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Ask the knowledge base</h2>
                            <div class="mt-5 grid gap-2 sm:grid-cols-2">
                                @foreach ([
                                    'Summarize the uploaded guidance documents.',
                                    'What are the key recommendations?',
                                    'Find references for emergency obstetric care.',
                                    'Which slides mention implementation steps?',
                                ] as $prompt)
                                    <button
                                        type="button"
                                        wire:click="$set('question', @js($prompt))"
                                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-left text-sm text-gray-700 shadow-sm transition hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                                    >
                                        {{ $prompt }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforelse

                <div wire:loading.flex wire:target="send" class="rag-chat-row">
                    <article
                        class="rag-chat-message rag-chat-message-assistant"
                        x-init="scheduleScroll()"
                    >
                        <div class="rag-chat-role">Answer</div>
                        <div class="flex items-center gap-2 text-sm font-medium text-gray-500 dark:text-gray-300">
                            <span>Thinking</span>
                            <span class="rag-thinking" aria-hidden="true">
                                <span class="rag-thinking-dot"></span>
                                <span class="rag-thinking-dot"></span>
                                <span class="rag-thinking-dot"></span>
                            </span>
                        </div>
                    </article>
                </div>
            </div>

            <form
                wire:submit.prevent="send"
                class="rag-chat-composer"
                x-on:submit="scheduleScroll()"
            >
                @if ($error)
                    <div class="mb-3 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700 dark:border-danger-900 dark:bg-danger-950/30 dark:text-danger-300">
                        {{ $error }}
                    </div>
                @endif

                <div class="rag-chat-input-row">
                    <label>
                        <span class="sr-only">Question</span>
                        <textarea
                            wire:model.live="question"
                            data-rag-chat-input
                            rows="1"
                            maxlength="4000"
                            class="rag-chat-textarea"
                            placeholder="Message the knowledge base..."
                            wire:loading.attr="disabled"
                            wire:target="send"
                        ></textarea>
                    </label>

                    <x-filament::button
                        type="submit"
                        icon="heroicon-o-paper-airplane"
                        wire:loading.attr="disabled"
                        wire:target="send"
                    >
                        Send
                    </x-filament::button>
                </div>
            </form>
        </section>
    </div>

    <script>
        (() => {
            const selector = '[data-rag-chat-input]';
            const maxRows = 6;
            const resize = (textarea) => {
                if (! textarea?.matches?.(selector)) {
                    return;
                }

                const lineHeight = Number.parseFloat(window.getComputedStyle(textarea).lineHeight) || 22;
                const verticalPadding = textarea.offsetHeight - textarea.clientHeight;
                textarea.style.height = 'auto';
                textarea.style.height = `${Math.min(textarea.scrollHeight, (lineHeight * maxRows) + verticalPadding)}px`;
            };

            document.addEventListener('input', (event) => resize(event.target), true);

            document.addEventListener('keydown', (event) => {
                const textarea = event.target;

                if (! textarea?.matches?.(selector) || event.key !== 'Enter' || event.shiftKey) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                textarea.closest('form')?.requestSubmit();
                window.dispatchEvent(new CustomEvent('rag-message-added'));
            }, true);

            const resizeAll = () => document.querySelectorAll(selector).forEach(resize);
            document.addEventListener('DOMContentLoaded', resizeAll);
            document.addEventListener('livewire:navigated', resizeAll);
            document.addEventListener('livewire:initialized', resizeAll);
            document.addEventListener('rag-message-added', resizeAll);
            setTimeout(resizeAll, 0);
        })();
    </script>
</x-filament-panels::page>
