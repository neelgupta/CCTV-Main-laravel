<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-950 leading-tight">Email Templates</h2>
            <p class="mt-0.5 text-sm text-slate-500">Manage templates, sender config, categories, attachments, and live preview from one screen.</p>
        </div>
    </x-slot>

    <div x-data="emailTemplateWorkspace(@js($builderConfig))" class="w-full py-3 sm:px-4 lg:px-6 space-y-4">
        <input type="file" x-ref="imageUploadInput" class="hidden" accept=".jpg,.jpeg,.png,.webp" @change="handleImageUpload($event)">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if(($errors ?? null)?->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        @unless($isBuilderPage)
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-950">Email Templates</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $templates->total() }} templates available for your marketing workflows.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('marketing.email-template.sender-config.page') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Sender Config
                    </a>
                    <a href="{{ route('marketing.email-template.categories.page') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Categories
                    </a>
                    <a href="{{ route('marketing.email-template.add') }}" class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        New Email Template
                    </a>
                </div>
            </div>

            <div class="p-4">
                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Template</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Sender Group</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Blocks</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Updated</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse($templates as $template)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-900">{{ $template->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">Subject: {{ $template->subject ?: 'No subject' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ $template->category }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ $template->senderGroup?->name ?: 'Default sender' }}</td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                            'bg-emerald-50 text-emerald-700' => $template->status === \App\Models\MarketingEmailTemplate::STATUS_ACTIVE,
                                            'bg-amber-50 text-amber-700' => $template->status !== \App\Models\MarketingEmailTemplate::STATUS_ACTIVE,
                                        ])>
                                            {{ ucfirst($template->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ count($template->blocks ?? []) }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-500">{{ $template->updated_at?->format('d M Y, h:i A') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="{{ route('marketing.email-template.preview', $template) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" title="Preview">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </a>
                                            <a href="{{ route('marketing.email-template.edit', $template) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" title="Edit">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                            </a>
                                            <form method="POST" action="{{ route('marketing.email-template.destroy', $template) }}" data-swal-confirm="Delete this email template? This action cannot be undone." data-swal-title="Confirm Delete" data-swal-confirm-button="Yes, Delete" data-swal-cancel-button="Cancel" data-swal-confirm-color="#DC2626" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 p-2 text-rose-600 transition hover:bg-rose-100" title="Delete">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No email templates yet. Click `New Email Template` to open the builder.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($templates->hasPages())
                    <div class="mt-4">
                        {{ $templates->links() }}
                    </div>
                @endif
            </div>
        </section>
        @endunless

        <section id="email-template-builder" x-show="showBuilder" x-cloak class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-950" x-text="isEditing ? 'Edit Email Template' : 'Create Email Template'"></h3>
                </div>

                @if($isBuilderPage)
                    <a href="{{ route('marketing.email-template') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Back To List
                    </a>
                @else
                    <button type="button" @click="closeBuilder()" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Close Builder
                    </button>
                @endif
            </div>

            <div class="grid gap-4 p-4 xl:grid-cols-[280px_minmax(0,1fr)_360px]">
                <aside class="rounded-2xl border border-slate-200 bg-gradient-to-b from-slate-50 to-white">
                    <div class="border-b border-slate-200 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Left Panel</div>
                        <h4 class="mt-2 text-base font-semibold text-slate-950">Template Assets</h4>
                        <p class="mt-1 text-sm text-slate-500">Drag the structure of your email from blocks, starters, and saved files.</p>
                    </div>

                    <div class="border-b border-slate-200 p-3">
                        <div class="grid grid-cols-3 gap-2 rounded-2xl bg-slate-100 p-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <button type="button" class="rounded-xl px-3 py-2 transition" :class="activeTab === 'blocks' ? 'bg-slate-950 text-white shadow-sm' : 'hover:bg-white'" @click="activeTab = 'blocks'">Blocks</button>
                            <button type="button" class="rounded-xl px-3 py-2 transition" :class="activeTab === 'quick' ? 'bg-slate-950 text-white shadow-sm' : 'hover:bg-white'" @click="activeTab = 'quick'">Quick</button>
                            <button type="button" class="rounded-xl px-3 py-2 transition" :class="activeTab === 'files' ? 'bg-slate-950 text-white shadow-sm' : 'hover:bg-white'" @click="activeTab = 'files'">Files</button>
                        </div>
                    </div>

                    <div class="max-h-[78vh] space-y-4 overflow-y-auto p-4">
                        <div x-show="activeTab === 'blocks'" class="space-y-3">
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-white/80 px-4 py-3 text-xs leading-5 text-slate-500">
                                BLOCKS tab ma thi add karo: Text Block, Image, Button Group, Custom Button, Topics, Topics Extra, Divider, Spacer.
                            </div>

                            <template x-for="item in libraryBlocks" :key="item.type">
                                <button type="button" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left transition hover:-translate-y-0.5 hover:border-cyan-300 hover:bg-cyan-50/70" @click="addBlock(item.type)">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-semibold text-slate-900" x-text="item.label"></div>
                                            <p class="mt-1 text-xs leading-5 text-slate-500" x-text="item.description"></p>
                                        </div>
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-lg leading-none text-slate-600">+</span>
                                    </div>
                                </button>
                            </template>
                        </div>

                        <div x-show="activeTab === 'quick'" class="space-y-3">
                            <template x-for="item in quickTemplates" :key="item.key">
                                <button type="button" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-emerald-300 hover:bg-emerald-50/70" @click="applyQuickTemplate(item.key)">
                                    <div class="text-sm font-semibold text-slate-900" x-text="item.name"></div>
                                    <p class="mt-1 text-xs leading-5 text-slate-500" x-text="item.description"></p>
                                </button>
                            </template>
                        </div>

                        <div x-show="activeTab === 'files'" class="space-y-4">
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="text-sm font-semibold text-slate-900">Attachments</div>
                                <p class="mt-1 text-xs text-slate-500">Upload files here, then reuse them inside your email blocks.</p>

                                <form method="POST" action="{{ route('marketing.email-template.files.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                                    @csrf
                                    <input type="file" name="attachment" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700">
                                    <button type="submit" class="inline-flex items-center rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-700">
                                        Add Files
                                    </button>
                                </form>
                            </div>

                            <template x-if="!mediaFiles.length">
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">
                                    No uploaded attachments yet.
                                </div>
                            </template>

                            <template x-for="file in mediaFiles" :key="file.id || file.url">
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-semibold text-slate-900" x-text="file.name"></div>
                                            <p class="mt-1 text-xs text-slate-500" x-text="file.meta"></p>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50" @click="insertFileImage(file.url)" title="Use in Email">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                                            </button>
                                            <form method="POST" :action="file.delete_url" data-swal-confirm="Delete this attachment? This action cannot be undone." data-swal-title="Confirm Delete" data-swal-confirm-button="Yes, Delete" data-swal-cancel-button="Cancel" data-swal-confirm-color="#DC2626" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-500 transition hover:bg-rose-100" title="Delete Attachment">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </aside>

                <section class="space-y-4">
                    <form id="email-template-form" method="POST" :action="formAction" @submit="syncBeforeSubmit" class="space-y-4">
                        @csrf
                        <template x-if="isEditing">
                            <input type="hidden" name="_method" value="PATCH">
                        </template>
                        <input type="hidden" name="name" :value="template.name || template.subject || 'Email Template'">
                        <input type="hidden" name="status" :value="template.status">
                        <input type="hidden" name="sender_group_id" :value="template.sender_group_id">
                        <input type="hidden" name="preheader" :value="template.preheader">
                        <input type="hidden" name="blocks_json" :value="serializedBlocks">

                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-200 px-5 py-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Center Panel</div>
                                <h4 class="mt-2 text-base font-semibold text-slate-950">Template Details</h4>
                            </div>

                            <div class="space-y-5 p-5">
                                <div class="grid gap-5 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Subject Line</label>
                                        <input x-model="template.subject" name="subject" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" :placeholder="defaultSubjectPlaceholder">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Category</label>
                                        <select x-model="template.category_id" name="category_id" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                            <option value="">Select category</option>
                                            <template x-for="category in categories" :key="category.id">
                                                <option :value="String(category.id)" x-text="category.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Reply-To</label>
                                        <input x-model="senderConfig.reply_to_email" name="reply_to_email" type="email" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                    </div>
                                    <div>
                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Default CC</label>
                                        <input x-model="senderConfig.cc_email" name="cc_email" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Default BCC</label>
                                        <input x-model="senderConfig.bcc_email" name="bcc_email" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Builder Flow</div>
                                    <h4 class="mt-1 text-base font-semibold text-slate-950">Email Blocks</h4>
                                </div>
                                <div class="text-xs font-semibold text-slate-500"><span x-text="blocks.length"></span> blocks</div>
                            </div>

                            <div class="max-h-[72vh] space-y-3 overflow-y-auto p-4">
                                <template x-if="!blocks.length">
                                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">Choose blocks from the left sidebar to start building your email.</div>
                                </template>

                                <template x-for="(block, index) in blocks" :key="block.id">
                                    <article class="rounded-2xl border p-4 transition" :class="selectedBlockId === block.id ? 'border-cyan-300 bg-cyan-50/60 shadow-sm' : 'border-slate-200 bg-slate-50/70'">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500" x-text="block.type"></span>
                                                    <span class="text-xs text-slate-400">Block <span x-text="index + 1"></span></span>
                                                </div>
                                                <button type="button" class="mt-3 text-left" @click="selectBlock(block.id)">
                                                    <div class="text-sm font-semibold text-slate-900" x-text="block.label"></div>
                                                </button>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50" @click="moveBlock(index, -1)" title="Move Up">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"/></svg>
                                                </button>
                                                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50" @click="moveBlock(index, 1)" title="Move Down">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                                                </button>
                                                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-500 transition hover:bg-rose-100" @click="removeBlock(block.id)" title="Delete Block">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mt-4 space-y-4 rounded-2xl border border-slate-200 bg-white p-4">
                                            <template x-if="block.type === 'text'">
                                                <div>
                                                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                                                        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-3 py-2 text-slate-500">
                                                            <button type="button" class="rounded px-2 py-1 text-sm font-semibold hover:bg-slate-100" @click="execTextCommand(block.id, 'bold')">B</button>
                                                            <button type="button" class="rounded px-2 py-1 text-sm italic hover:bg-slate-100" @click="execTextCommand(block.id, 'italic')">I</button>
                                                            <button type="button" class="rounded px-2 py-1 text-sm underline hover:bg-slate-100" @click="execTextCommand(block.id, 'underline')">U</button>
                                                            <button type="button" class="rounded px-2 py-1 text-sm line-through hover:bg-slate-100" @click="execTextCommand(block.id, 'strikeThrough')">S</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="execTextCommand(block.id, 'insertOrderedList')">1.</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="execTextCommand(block.id, 'justifyLeft')">Left</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="execTextCommand(block.id, 'justifyCenter')">Center</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="execTextCommand(block.id, 'justifyRight')">Right</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="execTextCommand(block.id, 'justifyFull')">Justify</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="execTextCommand(block.id, 'outdent')">Out</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="execTextCommand(block.id, 'indent')">In</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="execTextCommand(block.id, 'insertUnorderedList')">List</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="formatTextBlock(block.id, 'p')">P</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="formatTextBlock(block.id, 'h1')">H1</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="formatTextBlock(block.id, 'h2')">H2</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="formatTextBlock(block.id, 'h3')">H3</button>
                                                            <select class="rounded border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600" @change="applyFontFamily(block.id, $event.target.value)">
                                                                <option value="">Font</option>
                                                                <option value="Arial">Arial</option>
                                                                <option value="Georgia">Georgia</option>
                                                                <option value="Tahoma">Tahoma</option>
                                                                <option value="Verdana">Verdana</option>
                                                            </select>
                                                            <select class="rounded border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600" @change="applyFontSize(block.id, $event.target.value)">
                                                                <option value="">16px</option>
                                                                <option value="2">13px</option>
                                                                <option value="3">16px</option>
                                                                <option value="4">18px</option>
                                                                <option value="5">24px</option>
                                                            </select>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="applyTextColor(block.id)">A</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="createTextLink(block.id)">Link</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="execTextCommand(block.id, 'removeFormat')">Clear</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="insertTextVariable(block.id, salutationToken)">Vars</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="insertTextVariable(block.id, conferenceToken)">Conf</button>
                                                            <button type="button" class="rounded px-2 py-1 text-xs hover:bg-slate-100" @click="insertTextVariable(block.id, `${openToken}full_name${closeToken}`)">Name</button>
                                                        </div>
                                                        <div
                                                            class="min-h-[220px] px-4 py-4 text-[15px] leading-8 text-slate-900 outline-none"
                                                            contenteditable="true"
                                                            :data-editor-id="block.id"
                                                            x-init="$el.innerHTML = formatEditorContent(block.content || '')"
                                                            @focus="activeEditorBlockId = block.id"
                                                            @input="updateTextBlockContent(block, $event)"
                                                        ></div>
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="block.type === 'image'">
                                                <div class="space-y-4">
                                                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                                        <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Image Preview</div>
                                                        <div :style="`text-align:${block.align || 'center'};`">
                                                            <template x-if="block.url">
                                                                <img
                                                                    :src="block.url"
                                                                    :alt="block.alt || 'Preview image'"
                                                                    :style="`display:inline-block;width:${Number(block.width) || 100}%;height:${Number(block.height) || 240}px;object-fit:cover;border-radius:12px;border:1px solid #e2e8f0;background:#fff;`"
                                                                >
                                                            </template>
                                                            <template x-if="!block.url">
                                                                <div
                                                                    class="inline-flex items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white text-sm text-slate-400"
                                                                    :style="`width:${Number(block.width) || 100}%;height:${Number(block.height) || 240}px;`"
                                                                >
                                                                    Upload or paste an image URL
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>

                                                    <div class="flex gap-2">
                                                        <input x-model="block.url" @input="syncBeforeSubmit()" type="text" placeholder="Image URL..." class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                        <button type="button" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="applyImageUrl(block)">Set</button>
                                                        <button type="button" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="openImageUpload(block.id)" :disabled="isUploadingImage">
                                                            <span x-text="isUploadingImage && uploadTargetBlockId === block.id ? 'Uploading...' : 'Upload'"></span>
                                                        </button>
                                                    </div>

                                                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-xs font-semibold text-slate-400">Align:</span>
                                                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-slate-500 transition" :class="block.align === 'left' ? 'border-cyan-600 bg-cyan-600 text-white' : 'border-slate-300 bg-white hover:bg-slate-50'" @click="block.align = 'left'; syncBeforeSubmit()">
                                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h10"/><path d="M4 12h16"/><path d="M4 18h10"/></svg>
                                                            </button>
                                                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-slate-500 transition" :class="block.align === 'center' ? 'border-cyan-600 bg-cyan-600 text-white' : 'border-slate-300 bg-white hover:bg-slate-50'" @click="block.align = 'center'; syncBeforeSubmit()">
                                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 6h10"/><path d="M4 12h16"/><path d="M7 18h10"/></svg>
                                                            </button>
                                                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-slate-500 transition" :class="block.align === 'right' ? 'border-cyan-600 bg-cyan-600 text-white' : 'border-slate-300 bg-white hover:bg-slate-50'" @click="block.align = 'right'; syncBeforeSubmit()">
                                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 6h10"/><path d="M4 12h16"/><path d="M10 18h10"/></svg>
                                                            </button>
                                                        </div>

                                                        <div class="flex flex-1 items-center gap-3 md:justify-end">
                                                            <span class="text-xs font-semibold text-slate-400" x-text="`Width: ${Number(block.width) || 100}%`"></span>
                                                            <input x-model="block.width" @input="syncBeforeSubmit()" type="range" min="10" max="100" class="h-2 w-full max-w-[260px] cursor-pointer appearance-none rounded-full bg-emerald-600/80 accent-emerald-600">
                                                        </div>
                                                    </div>

                                                    <div class="grid gap-4 md:grid-cols-2">
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Alt Text</label>
                                                            <input x-model="block.alt" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Height</label>
                                                            <input x-model="block.height" @input="syncBeforeSubmit()" type="number" min="80" max="480" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="block.type === 'button' || block.type === 'custom_button'">
                                                <div class="grid gap-4 md:grid-cols-2">
                                                    <div>
                                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Button Text</label>
                                                        <input x-model="block.text" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                    </div>
                                                    <div>
                                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Button URL</label>
                                                        <input x-model="block.url" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                    </div>
                                                    <div>
                                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Background</label>
                                                        <input x-model="block.bg" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                    </div>
                                                    <div>
                                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Text Color</label>
                                                        <input x-model="block.color" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="block.type === 'button_group'">
                                                <div class="space-y-4">
                                                    <template x-for="(button, buttonIndex) in block.buttons" :key="`${block.id}-inline-button-${buttonIndex}`">
                                                        <div class="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-2">
                                                            <div>
                                                                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400" x-text="`Button ${buttonIndex + 1} Text`"></label>
                                                                <input x-model="button.text" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400" x-text="`Button ${buttonIndex + 1} URL`"></label>
                                                                <input x-model="button.url" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Background</label>
                                                                <input x-model="button.bg" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Text Color</label>
                                                                <input x-model="button.color" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>

                                            <template x-if="block.type === 'topics' || block.type === 'topics_extra'">
                                                <div class="space-y-4">
                                                    <div class="grid gap-4 md:grid-cols-2">
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Intro Sentence</label>
                                                            <input x-model="block.title" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Topics Preview</label>
                                                            <input x-model="block.description" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                        </div>
                                                    </div>
                                                    <template x-for="(item, itemIndex) in block.items" :key="`${block.id}-inline-topic-${itemIndex}`">
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400" x-text="`Topic ${itemIndex + 1}`"></label>
                                                            <input x-model="block.items[itemIndex]" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>

                                            <template x-if="block.type === 'divider'">
                                                <div class="grid gap-4 md:grid-cols-2">
                                                    <div>
                                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Line Color</label>
                                                        <input x-model="block.color" @input="syncBeforeSubmit()" type="text" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                    </div>
                                                    <div>
                                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Weight</label>
                                                        <input x-model="block.weight" @input="syncBeforeSubmit()" type="number" min="1" max="6" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="block.type === 'spacer'">
                                                <div>
                                                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Spacer Height</label>
                                                    <input x-model="block.height" @input="syncBeforeSubmit()" type="number" min="4" max="180" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                                </div>
                                            </template>
                                        </div>
                                    </article>
                                </template>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700" x-text="isEditing ? 'Update Template' : 'Create Template'"></button>
                        </div>
                    </form>
                </section>

                <aside class="rounded-2xl border border-slate-200 bg-slate-50/80 xl:sticky xl:top-4 xl:self-start">
                    <div class="border-b border-slate-200 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Right Panel</div>
                        <h4 class="mt-1 text-base font-semibold text-slate-950">Live Email Preview</h4>
                        <p class="mt-1 text-sm text-slate-500">Final HTML updates in real time.</p>
                    </div>

                    <div class="space-y-4 p-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-3">
                            <div class="border-b border-slate-200 px-4 py-3">
                                <div class="text-xs font-semibold text-slate-400">Subject</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900" x-text="template.subject || 'No subject yet'"></div>
                            </div>
                            <div class="max-h-[62vh] overflow-y-auto px-4 py-4">
                                <div x-html="renderPreview()"></div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

        </section>
    </div>

    @push('scripts')
        <script>
            function emailTemplateWorkspace(config) {
                const openToken = String.fromCharCode(123, 123);
                const closeToken = String.fromCharCode(125, 125);
                const conferenceToken = `${openToken}conference_name${closeToken}`;
                const salutationToken = `${openToken}salutation${closeToken}`;
                const firstNameToken = `${openToken}first_name${closeToken}`;
                const quickTemplateBlocks = {
                    invitation() {
                        const stamp = Date.now();
                        return [
                            { id: `image-${stamp}-hero`, type: 'image', label: 'Hero Image', summary: 'Conference banner or key visual.', url: 'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80', alt: 'Conference banner', height: 240 },
                            { id: `text-${stamp}-intro`, type: 'text', label: 'Invitation Copy', summary: 'Greeting, invite note, and session hook.', content: `Dear Dr. John,\n\nGreetings from Scientific Committee!\n\nIt is our privilege to invite you to join ${conferenceToken} and share your insights during the conference.` },
                            { id: `button-${stamp}-cta`, type: 'custom_button', label: 'Primary CTA', summary: 'Accept or respond to the invite.', text: 'Accept Invitation', url: '#', bg: '#0f766e', color: '#ffffff' },
                            { id: `text-${stamp}-footer`, type: 'text', label: 'Closing Note', summary: 'Closing signature and quick details.', content: 'Warm regards,\nOrganizing Committee\n\nYour Session Details:\n• Day: Day 1\n• Time: 10:00 AM - 11:00 AM\n• Hall: Hall A' },
                        ];
                    },
                    'session-details'() {
                        return [
                            { id: `text-${Date.now()}-session`, type: 'text', label: 'Session Details', summary: 'Agenda-style section for speaker communication.', content: 'Your Session Details:\n• Session: Advances in Minimally Invasive Surgery\n• Day: Day 1 (15 March 2026)\n• Time: 10:00 AM - 11:00 AM\n• Hall: Hall A\n• Topic: Advanced Laparoscopy' },
                        ];
                    },
                    signature() {
                        return [
                            { id: `text-${Date.now()}-signature`, type: 'text', label: 'Signature', summary: 'Warm closing and sign-off.', content: 'Warm regards,\nOrganizing Committee' },
                        ];
                    },
                };

                return {
                    activeTab: 'blocks',
                    defaultSubjectPlaceholder: `Invitation to join ${conferenceToken}`,
                    showBuilder: Boolean(config.showBuilder),
                    libraryBlocks: config.libraryBlocks || [],
                    quickTemplates: config.quickTemplates || [],
                    mediaFiles: config.mediaFiles || [],
                    senderConfig: config.senderConfig || {},
                    templates: config.templates || [],
                    categories: config.categories || [],
                    senderGroups: config.senderGroups || [],
                    formActions: config.formActions || {},
                    selectedBlockId: null,
                    activeEditorBlockId: null,
                    uploadTargetBlockId: null,
                    isUploadingImage: false,
                    template: { id: null, name: '', category_id: '', subject: '', preheader: '', status: 'active', sender_group_id: '' },
                    blocks: [],
                    serializedBlocks: '[]',
                    init() {
                        this.loadTemplate(config.initialTemplate || null);
                        if (this.showBuilder) {
                            this.$nextTick(() => this.scrollToBuilder());
                        }
                    },
                    get isEditing() {
                        return Boolean(this.template.id);
                    },
                    get formAction() {
                        return this.isEditing
                            ? (this.formActions.updateBase || '').replace('__ID__', this.template.id)
                            : (this.formActions.store || '');
                    },
                    normalizeTemplate(payload) {
                        return {
                            id: payload?.id || null,
                            name: payload?.name || 'Invite Topic - No Extra Topic Change',
                            category_id: payload?.category_id ? String(payload.category_id) : '',
                            subject: payload?.subject || `Email subject... Use ${openToken}variables${closeToken}`,
                            preheader: payload?.preheader || '',
                            status: payload?.status || 'active',
                            sender_group_id: payload?.sender_group_id ? String(payload.sender_group_id) : '',
                        };
                    },
                    loadTemplate(payload) {
                        this.template = this.normalizeTemplate(payload);
                        this.blocks = Array.isArray(payload?.blocks) && payload.blocks.length ? payload.blocks : this.defaultStarterBlocks();
                        this.selectedBlockId = this.blocks[0]?.id || null;
                        this.syncBeforeSubmit();
                    },
                    defaultStarterBlocks() {
                        const stamp = Date.now();
                        return [
                            { id: `image-${stamp}-hero`, type: 'image', label: 'Image', summary: 'Upload or paste an image URL', url: '', alt: 'Invitation banner', height: 220, align: 'center', width: 100 },
                            { id: `text-${stamp}-intro`, type: 'text', label: 'Text', summary: 'Greeting and invitation copy.', content: `Dear ${salutationToken} ${firstNameToken},\n\nGreetings! We are pleased to invite you to ${conferenceToken}.` },
                            { id: `topics-${stamp}-main`, type: 'topics', label: 'Topics', summary: 'Auto-populated speaker topics preview.', title: 'Your assigned topic(s) for the conference:', description: "Resolves to speaker's approved topics:", items: ['Advanced Laparoscopic Techniques', 'Robotic Surgery in Urology', 'All of the above'] },
                            { id: `buttons-${stamp}-main`, type: 'button_group', label: 'Buttons', summary: 'Accept Invitation and Decline buttons.', buttons: [{ text: 'Accept Invitation', url: '#', bg: '#0f9a8a', color: '#ffffff' }, { text: 'Decline', url: '#', bg: '#dc2626', color: '#ffffff' }] },
                            { id: `text-${stamp}-footer`, type: 'text', label: 'Text', summary: 'Warm closing text.', content: 'Warm regards,\nOrganizing Committee' },
                            { id: `image-${stamp}-signature`, type: 'image', label: 'Image', summary: 'Upload or paste an image URL', url: '', alt: 'Signature image', height: 160, align: 'left', width: 100 },
                        ];
                    },
                    selectedSenderGroup() {
                        return this.senderGroups.find((group) => String(group.id) === String(this.template.sender_group_id)) || null;
                    },
                    senderMeta() {
                        const group = this.selectedSenderGroup();
                        return {
                            reply_to_email: group?.sender_email || this.senderConfig.reply_to_email || 'reply@example.com',
                            cc_email: group?.cc_email || this.senderConfig.cc_email || 'cc1@example.com',
                            bcc_email: group?.bcc_email || this.senderConfig.bcc_email || 'bcc@example.com',
                        };
                    },
                    startNewTemplate() {
                        this.showBuilder = true;
                        this.loadTemplate(null);
                        this.$nextTick(() => this.scrollToBuilder());
                    },
                    closeBuilder() {
                        this.showBuilder = false;
                    },
                    editTemplate(id) {
                        const found = this.templates.find((item) => Number(item.id) === Number(id));
                        if (!found) return;
                        this.showBuilder = true;
                        this.loadTemplate(found);
                        this.$nextTick(() => this.scrollToBuilder());
                    },
                    scrollToBuilder() {
                        document.getElementById('email-template-builder')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    },
                    selectBlock(id) {
                        this.selectedBlockId = id;
                    },
                    selectedBlock() {
                        return this.blocks.find((block) => block.id === this.selectedBlockId) || null;
                    },
                    addBlock(type) {
                        const block = this.buildBlock(type);
                        this.blocks.push(block);
                        this.selectedBlockId = block.id;
                        this.syncBeforeSubmit();
                    },
                    buildBlock(type) {
                        const id = `${type}-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`;
                        if (type === 'image') return { id, type, label: 'Hero Image', summary: 'Use this area for event branding or campaign visual.', url: 'https://dummyimage.com/1200x480/e2e8f0/0f172a&text=Email+Banner', alt: 'Conference banner', height: 240 };
                        if (type === 'button') return { id, type, label: 'Primary CTA', summary: 'A clear next step for the recipient.', text: 'Accept Invitation', url: '#', bg: '#0f766e', color: '#ffffff' };
                        if (type === 'custom_button') return { id, type, label: 'Custom Button', summary: 'Single custom call-to-action button.', text: 'Register Now', url: '#', bg: '#0f766e', color: '#ffffff' };
                        if (type === 'button_group') return { id, type, label: 'Button Group', summary: 'Two CTA buttons for multiple response paths.', buttons: [{ text: 'Accept', url: '#', bg: '#0f766e', color: '#ffffff' }, { text: 'Maybe Later', url: '#', bg: '#e2e8f0', color: '#0f172a' }] };
                        if (type === 'topics') return { id, type, label: 'Topics', summary: 'Compact list of discussion points.', title: 'Conference Topics', description: 'Highlight the most important themes in this invite.', items: ['Future of minimally invasive care', 'Live surgical discussion', 'Panel with keynote speakers'] };
                        if (type === 'topics_extra') return { id, type, label: 'Topics Extra', summary: 'Detailed agenda list with extra context.', title: 'Topics Extra', description: 'Use for extended agenda notes or breakout details.', items: ['Registration opens at 8:30 AM', 'Networking with specialty leads', 'Hands-on showcase and Q&A', 'Closing remarks and certificates'] };
                        if (type === 'divider') return { id, type, label: 'Divider', summary: 'Subtle separator for cleaner sections.', color: '#e2e8f0', weight: 1 };
                        if (type === 'spacer') return { id, type, label: 'Spacer', summary: 'Adds vertical breathing room.', height: 24 };
                        return { id, type: 'text', label: 'Text Block', summary: 'Editable text content for the email body.', content: `Dear ${salutationToken} ${firstNameToken},\n\nGreetings!\n\nWe are delighted to invite you to join ${conferenceToken}.` };
                    },
                    moveBlock(index, direction) {
                        const targetIndex = index + direction;
                        if (targetIndex < 0 || targetIndex >= this.blocks.length) return;
                        const current = this.blocks[index];
                        this.blocks.splice(index, 1);
                        this.blocks.splice(targetIndex, 0, current);
                        this.syncBeforeSubmit();
                    },
                    removeBlock(id) {
                        this.blocks = this.blocks.filter((block) => block.id !== id);
                        if (this.selectedBlockId === id) {
                            this.selectedBlockId = this.blocks[0]?.id || null;
                        }
                        this.syncBeforeSubmit();
                    },
                    applyQuickTemplate(key) {
                        const factory = quickTemplateBlocks[key];
                        if (!factory) return;
                        this.blocks = factory();
                        this.selectedBlockId = this.blocks[0]?.id || null;
                        this.syncBeforeSubmit();
                    },
                    insertFileImage(url) {
                        this.addBlock('image');
                        const block = this.selectedBlock();
                        if (block) {
                            block.url = url;
                        }
                        this.syncBeforeSubmit();
                    },
                    openImageUpload(blockId) {
                        this.uploadTargetBlockId = blockId;
                        this.$refs.imageUploadInput.value = '';
                        this.$refs.imageUploadInput.click();
                    },
                    applyImageUrl(block) {
                        if (!block) return;
                        block.url = String(block.url || '').trim();
                        this.syncBeforeSubmit();
                    },
                    applyUploadedImageDimensions(block, dimensions) {
                        const imageWidth = Number(dimensions?.width || 0);
                        const imageHeight = Number(dimensions?.height || 0);
                        if (!block || imageWidth <= 0 || imageHeight <= 0) return;

                        const previewCanvasWidth = 560;
                        const targetWidth = Math.min(imageWidth, previewCanvasWidth);
                        const widthPercent = Math.max(10, Math.min(100, Math.round((targetWidth / previewCanvasWidth) * 100)));
                        const scaledHeight = Math.max(80, Math.min(480, Math.round((imageHeight * targetWidth) / imageWidth)));

                        block.width = widthPercent;
                        block.height = scaledHeight;
                    },
                    formatEditorContent(value) {
                        const content = String(value || '');
                        if (content.includes('<')) return content;
                        return this.escapeHtml(content).replace(/\n/g, '<br>');
                    },
                    updateTextBlockContent(block, event) {
                        block.content = event.target.innerHTML;
                        this.syncBeforeSubmit();
                    },
                    focusTextEditor(blockId) {
                        const editor = document.querySelector(`[data-editor-id="${blockId}"]`);
                        editor?.focus();
                    },
                    execTextCommand(blockId, command, value = null) {
                        this.focusTextEditor(blockId);
                        document.execCommand(command, false, value);
                        this.syncEditorHtml(blockId);
                    },
                    createTextLink(blockId) {
                        const url = window.prompt('Enter link URL');
                        if (!url) return;
                        this.execTextCommand(blockId, 'createLink', url);
                    },
                    applyFontFamily(blockId, fontName) {
                        if (!fontName) return;
                        this.execTextCommand(blockId, 'fontName', fontName);
                    },
                    applyFontSize(blockId, size) {
                        if (!size) return;
                        this.execTextCommand(blockId, 'fontSize', size);
                    },
                    applyTextColor(blockId) {
                        const color = window.prompt('Enter text color', '#0f172a');
                        if (!color) return;
                        this.execTextCommand(blockId, 'foreColor', color);
                    },
                    formatTextBlock(blockId, tag) {
                        this.focusTextEditor(blockId);
                        document.execCommand('formatBlock', false, tag);
                        this.syncEditorHtml(blockId);
                    },
                    insertTextVariable(blockId, token) {
                        this.focusTextEditor(blockId);
                        document.execCommand('insertText', false, `${token} `);
                        this.syncEditorHtml(blockId);
                    },
                    syncEditorHtml(blockId) {
                        const editor = document.querySelector(`[data-editor-id="${blockId}"]`);
                        const block = this.blocks.find((item) => item.id === blockId);
                        if (!editor || !block) return;
                        block.content = editor.innerHTML;
                        this.syncBeforeSubmit();
                    },
                    async handleImageUpload(event) {
                        const file = event.target.files?.[0];
                        if (!file || !this.uploadTargetBlockId) return;

                        this.isUploadingImage = true;

                        try {
                            const formData = new FormData();
                            formData.append('attachment', file);

                            const response = await fetch(this.formActions.uploadFile, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                    'Accept': 'application/json',
                                },
                                body: formData,
                                credentials: 'same-origin',
                            });

                            const payload = await response.json();

                            if (!response.ok) {
                                throw new Error(payload?.message || 'Upload failed.');
                            }

                            const block = this.blocks.find((item) => item.id === this.uploadTargetBlockId);
                            if (block && payload?.file?.url) {
                                block.url = payload.file.url;
                                this.applyUploadedImageDimensions(block, payload.file.dimensions);
                            }

                            if (payload?.file) {
                                this.mediaFiles.unshift(payload.file);
                            }

                            this.syncBeforeSubmit();
                        } catch (error) {
                            window.alert(error?.message || 'Image upload failed.');
                        } finally {
                            this.isUploadingImage = false;
                            this.uploadTargetBlockId = null;
                            event.target.value = '';
                        }
                    },
                    syncBeforeSubmit() {
                        const subject = String(this.template.subject || '').trim();
                        this.template.name = subject || this.template.name || 'Email Template';
                        this.serializedBlocks = JSON.stringify(this.blocks);
                    },
                    escapeHtml(value) {
                        return String(value || '')
                            .replaceAll('&', '&amp;')
                            .replaceAll('<', '&lt;')
                            .replaceAll('>', '&gt;')
                            .replaceAll('"', '&quot;')
                            .replaceAll("'", '&#039;');
                    },
                    renderButtonHtml(button, extraStyle = '') {
                        return `<a href="${this.escapeHtml(button?.url || '#')}" style="display:inline-block;padding:12px 20px;border-radius:12px;background:${this.escapeHtml(button?.bg || '#0f766e')};color:${this.escapeHtml(button?.color || '#ffffff')};font-size:14px;font-weight:700;text-decoration:none;${extraStyle}">${this.escapeHtml(button?.text || 'Click Here')}</a>`;
                    },
                    renderTopicsHtml(block) {
                        const title = block.title ? `<div style="font-size:15px;color:#0f172a;margin:0 0 12px;">${this.escapeHtml(block.title)}</div>` : '';
                        const description = block.description ? `<div style="font-size:12px;font-style:italic;line-height:1.6;color:#64748b;margin:0 0 12px;">${this.escapeHtml(block.description)}</div>` : '';
                        const items = (Array.isArray(block.items) ? block.items : [])
                            .filter(Boolean)
                            .map((item) => `<li style="margin:0 0 8px;color:#0f172a;list-style:none;"><span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:#0f9a8a;color:#ffffff;text-align:center;line-height:14px;font-size:10px;margin-right:8px;">✓</span>${this.escapeHtml(item)}</li>`)
                            .join('');
                        return `<div style="margin:0 0 18px;padding:18px 20px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;">${title}${description}<ul style="padding-left:0;margin:0;">${items}</ul></div>`;
                    },
                    renderPreview() {
                        return this.renderPreviewBlocks(this.blocks);
                    },
                    renderPreviewBlocks(blocks) {
                        return (blocks || []).map((block) => {
                            if (block.type === 'image') {
                                const alignment = block.align === 'right' ? 'right' : (block.align === 'left' ? 'left' : 'center');
                                const width = Number(block.width) || 100;
                                const placeholder = `<div style="display:flex;align-items:center;justify-content:center;width:${width}%;height:${Number(block.height) || 240}px;border:1px dashed #cbd5e1;border-radius:12px;color:#94a3b8;font-size:13px;background:#ffffff;">Upload or paste an image URL</div>`;
                                const image = block.url
                                    ? `<img src="${this.escapeHtml(block.url)}" alt="${this.escapeHtml(block.alt)}" style="display:block;width:${width}%;height:${Number(block.height) || 240}px;object-fit:cover;border-radius:12px;">`
                                    : placeholder;
                                return `<div style="margin:0 0 18px;text-align:${alignment};">${image}</div>`;
                            }
                            if (block.type === 'button' || block.type === 'custom_button') return `<div style="margin:0 0 18px;text-align:center;">${this.renderButtonHtml(block)}</div>`;
                            if (block.type === 'button_group') {
                                const buttons = (Array.isArray(block.buttons) ? block.buttons : [])
                                    .map((button) => this.renderButtonHtml(button, 'margin:0 6px 8px;'))
                                    .join('');
                                return `<div style="margin:0 0 18px;text-align:center;">${buttons}</div>`;
                            }
                            if (block.type === 'topics' || block.type === 'topics_extra') return this.renderTopicsHtml(block);
                            if (block.type === 'divider') return `<div style="margin:0 0 18px;border-top:${Number(block.weight) || 1}px solid ${this.escapeHtml(block.color)};"></div>`;
                            if (block.type === 'spacer') return `<div style="height:${Number(block.height) || 24}px;"></div>`;
                            return `<div style="margin:0 0 18px;color:#0f172a;font-size:15px;line-height:1.8;">${this.formatEditorContent(block.content)}</div>`;
                        }).join('');
                    },
                    renderHtmlDocument(blocks, preheader) {
                        return [
                            '<!doctype html>',
                            '<html>',
                            '<body style="margin:0;padding:24px;background:#f8fafc;font-family:Inter,Arial,sans-serif;">',
                            '  <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;padding:24px;">',
                            `    <div style="font-size:13px;color:#64748b;margin-bottom:12px;">${this.escapeHtml(preheader)}</div>`,
                            `    ${this.renderPreviewBlocks(blocks)}`,
                            '  </div>',
                            '</body>',
                            '</html>',
                        ].join('\n');
                    },
                    renderHtmlSource() {
                        return this.renderHtmlDocument(this.blocks, this.template.preheader);
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
