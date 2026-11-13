@props([
    'eyebrow' => '',
    'title' => 'Run your CCTV & field service business with total visibility.',
    'description' => 'Track installations, AMC visits, live monitoring, and technicians from one branded workspace built for CCTV and service teams.',
    'highlights' => [],
    'stats' => [],
])

@php
    $highlights = collect($highlights)->filter()->values();
    $stats = collect($stats)->filter()->values();

    $slides = [
        [
            'badge' => 'camera',
            'label' => 'Live Monitoring',
            'client' => 'Aravali Residency, Sector 12',
            'meta' => ['4K NVR · 30 Days Storage', 'Last checked 2 mins ago'],
            'rows' => [
                ['label' => 'Cameras Installed', 'value' => '16'],
                ['label' => 'NVR Channels', 'value' => '16 CH'],
                ['label' => 'Recording Storage', 'value' => '30 Days'],
            ],
            'total' => ['label' => 'Health Status', 'value' => 'All Online'],
            'stamp' => 'LIVE',
        ],
        [
            'badge' => 'wrench',
            'label' => 'AMC & Service',
            'client' => 'Kunal Saxena Enterprises',
            'meta' => ['AMC ID · 1222K89DU', 'Next visit scheduled'],
            'rows' => [
                ['label' => 'Service Charge', 'value' => '₹3,700'],
                ['label' => 'Discount 2%', 'value' => '-₹74'],
                ['label' => 'GST 18%', 'value' => '₹653'],
            ],
            'total' => ['label' => 'Grand Total', 'value' => '₹4,279'],
            'stamp' => 'PAID',
        ],
        [
            'badge' => 'shield',
            'label' => 'Installation Job',
            'client' => 'Blue Ridge Business Park',
            'meta' => ['Technician · Rahul V.', 'On-site now'],
            'rows' => [
                ['label' => 'Dome Cameras', 'value' => '8'],
                ['label' => 'Bullet Cameras', 'value' => '6'],
                ['label' => 'DVR/NVR Setup', 'value' => '1'],
            ],
            'total' => ['label' => 'Job Progress', 'value' => '80%'],
            'stamp' => 'ACTIVE',
        ],
    ];

    $badgeIcons = [
        'camera' => '<path d="M4 8a2 2 0 012-2h1.17a2 2 0 001.42-.59l.82-.82A2 2 0 0110.83 4h2.34a2 2 0 011.42.59l.82.82A2 2 0 0016.83 6H18a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V8z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="3.25" fill="none" stroke="currentColor" stroke-width="1.8"/>',
        'wrench' => '<path d="M14.7 6.3a4 4 0 00-5.4 4.86L4 16.5 5.5 18l5.34-5.3a4 4 0 004.86-5.4l-2.3 2.3-1.9-.4-.4-1.9 2.3-2.3z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round"/>',
        'shield' => '<path d="M12 3.5l6.5 2.4v5.2c0 4.2-2.7 7.6-6.5 8.9-3.8-1.3-6.5-4.7-6.5-8.9V5.9L12 3.5z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9 12l2 2 4-4.2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    ];
@endphp

<aside class="ww-auth-side hidden md:flex">
    <div class="ww-auth-side-pattern" aria-hidden="true"></div>
    <div class="ww-auth-side-orb ww-auth-side-orb-a" aria-hidden="true"></div>
    <div class="ww-auth-side-orb ww-auth-side-orb-b" aria-hidden="true"></div>

    <div class="ww-auth-side-content">
        <div>
            @if($eyebrow)
                <span class="ww-brand-eyebrow">{{ $eyebrow }}</span>
            @endif
            <h1 class="ww-brand-heading mt-3">{!! $title !!}</h1>
            <p class="ww-brand-copy">{{ $description }}</p>
        </div>
                    <div class="">
                        
<img src="assets/images/login/360_F_620732047_oK7OpoyxBp6qyk15WYAf1O5p9EKabk2g.jpg" alt="">
                       

                       

                       
                    </div>
        <!-- <div
            class="ww-slider"
            x-data="{ active: 0, total: {{ count($slides) }}, timer: null, go(i) { this.active = i; clearInterval(this.timer); this.timer = setInterval(() => { this.active = (this.active + 1) % this.total }, 4500) } }"
            x-init="timer = setInterval(() => { active = (active + 1) % total }, 4500)"
        >
            @foreach ($slides as $index => $slide)
                <div class="ww-slide-card" x-show="active === {{ $index }}" x-transition.opacity.duration.500ms @if($index > 0) x-cloak @endif>
                    <span class="ww-slide-badge">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" aria-hidden="true">{!! $badgeIcons[$slide['badge']] ?? '' !!}</svg>
                    </span>

                    
                </div>
            @endforeach

            <div class="ww-slider-dots">
                @foreach ($slides as $index => $slide)
                    <button
                        type="button"
                        class="ww-slider-dot"
                        :class="{ 'is-active': active === {{ $index }} }"
                        @click="go({{ $index }})"
                        aria-label="Show slide {{ $index + 1 }}"
                    ></button>
                @endforeach
            </div>
        </div> -->

        @if ($highlights->isNotEmpty())
            <div class="ww-highlight-list">
                @foreach ($highlights as $highlight)
                    <div class="ww-highlight-item">
                        <span class="ww-highlight-icon">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.2 7.2a1 1 0 01-1.415 0l-3-3a1 1 0 011.414-1.42l2.293 2.294 6.493-6.494a1 1 0 011.415 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <span>{{ $highlight }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($stats->isNotEmpty())
            <div class="ww-kpi-grid">
                @foreach ($stats as $stat)
                    <div class="ww-kpi-card">
                        <strong>{{ $stat['value'] ?? '' }}</strong>
                        <span>{{ $stat['label'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if (trim((string) $slot) !== '')
            <div class="ww-progress-card">
                {{ $slot }}
            </div>
        @endif
    </div>
</aside>
