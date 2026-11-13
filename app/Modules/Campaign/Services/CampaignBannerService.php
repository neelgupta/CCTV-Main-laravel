<?php

namespace App\Modules\Campaign\Services;

use App\Models\FestivalCampaign;
use App\Models\GeneratedBanner;
use App\Models\User;
use App\Services\AIBannerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CampaignBannerService
{
    private const FESTIVAL_IMAGE_ASSETS = [
        'diwali' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_064a0550843f4cdc016a3e187f20c4819188a2f2309e9291c6.png',
        ],
        'holi' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_064a0550843f4cdc016a3e18b7354881918897f2131c12ac7b.png',
        ],
        'christmas' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_03a18daafb7172e5016a3e167cbb58819194d13d26520b8f35.png',
        ],
        'navratri' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_064a0550843f4cdc016a3e19342fc481918376c5698ba63d1e.png',
        ],
        'eid' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_064a0550843f4cdc016a3e197084e8819185b8d22f9702fbf3.png',
        ],
        'raksha-bandhan' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_064a0550843f4cdc016a3e19bff600819193651f6251a32c80.png',
        ],
        'janmashtami' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_064a0550843f4cdc016a3e1b0e23ec81918412a2de7aea501f.png',
        ],
        'ganesh-chaturthi' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_07b5bd07db4d26b3016a3e160ed4c48191a4711da1ab6cb50c.png',
        ],
        'dussehra' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_064a0550843f4cdc016a3e1a29e8588191bc31850b0d9fb7e4.png',
        ],
        'guru-nanak-jayanti' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_064a0550843f4cdc016a3e1aa6ad708191b6f07d1e53ddfe7a.png',
        ],
        'bhai-dooj' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_03a18daafb7172e5016a3e16d0ffac8191af724cab3e8407a0.png',
        ],
        'default' => [
            'C:/Users/Nexcom Infotech/.codex/generated_images/019f0259-ad25-7cc2-a577-162191dd8706/ig_064a0550843f4cdc016a3e187f20c4819188a2f2309e9291c6.png',
        ],
    ];

    private const FESTIVAL_COPY_VARIANTS = [
        'diwali' => [
            ['headline' => 'Light Up Your Diwali Campaign', 'subtitle' => 'Share festive warmth, premium offers, and bright wishes this Diwali.', 'message' => 'Designed to help your brand stand out during the festival of lights.'],
            ['headline' => 'Celebrate Diwali with Grace and Joy', 'subtitle' => 'Bring festive elegance to every customer message and campaign touchpoint.', 'message' => 'Create a polished Diwali greeting that feels warm, modern, and memorable.'],
        ],
        'holi' => [
            ['headline' => 'Bring Color to Your Holi Greetings', 'subtitle' => 'Celebrate the joy of Holi with vibrant visuals and cheerful messaging.', 'message' => 'A lively festival banner that helps your seasonal promotion feel energetic and fresh.'],
            ['headline' => 'Make Your Holi Campaign Feel Festive', 'subtitle' => 'Use bold colors and bright copy to capture the spirit of the celebration.', 'message' => 'Built for colorful festive outreach across email, WhatsApp, and social campaigns.'],
        ],
        'christmas' => [
            ['headline' => 'Celebrate the Spirit of Christmas', 'subtitle' => 'Share warmth, joy, and goodwill with a polished holiday campaign banner.', 'message' => 'A refined festive design for heartfelt greetings and seasonal promotions.'],
            ['headline' => 'Send Warm Christmas Wishes in Style', 'subtitle' => 'Pair your festive message with elegant holiday branding and clean visuals.', 'message' => 'Made for memorable Christmas outreach that feels thoughtful and professional.'],
        ],
        'navratri' => [
            ['headline' => 'Celebrate Navratri with Festive Energy', 'subtitle' => 'Honor the season with vibrant visuals inspired by tradition and devotion.', 'message' => ''],
            ['headline' => 'Bring the Spirit of Navratri to Life', 'subtitle' => 'Share colorful greetings that reflect celebration, culture, and community.', 'message' => 'Built to give your Navratri campaign a festive and polished visual identity.'],
        ],
        'eid' => [
            ['headline' => 'Share Joyful Eid Greetings', 'subtitle' => 'Celebrate the occasion with graceful design, blessings, and thoughtful branding.', 'message' => 'An elegant Eid banner for meaningful festive communication with your audience.'],
            ['headline' => 'Celebrate Eid with Warmth and Blessings', 'subtitle' => 'Send polished festive wishes with a calm and premium visual style.', 'message' => 'Designed for brand-led Eid campaigns that feel respectful, festive, and refined.'],
        ],
        'raksha bandhan' => [
            ['headline' => 'Celebrate the Bond of Raksha Bandhan', 'subtitle' => 'Share heartfelt greetings inspired by family, tradition, and togetherness.', 'message' => 'A warm festive banner designed for meaningful seasonal outreach.'],
            ['headline' => 'Send Heartfelt Raksha Bandhan Wishes', 'subtitle' => 'Capture the spirit of sibling love with a festive and polished banner.', 'message' => 'Perfect for festive promotions and relationship-focused seasonal campaigns.'],
        ],
        'janmashtami' => [
            ['headline' => 'Celebrate Janmashtami with Devotion', 'subtitle' => 'Honor the festival with serene visuals, festive blessings, and elegant design.', 'message' => 'A graceful campaign banner inspired by tradition, joy, and spiritual warmth.'],
            ['headline' => 'Share Divine Janmashtami Greetings', 'subtitle' => 'Bring together festive energy, devotion, and polished seasonal branding.', 'message' => 'Crafted for festive messages that feel respectful, bright, and memorable.'],
        ],
        'ganesh chaturthi' => [
            ['headline' => 'Welcome Ganesh Chaturthi with Joy', 'subtitle' => 'Celebrate the season with blessings, festive colors, and polished campaign visuals.', 'message' => 'A thoughtful banner design for festive greetings and seasonal brand outreach.'],
            ['headline' => 'Celebrate Ganesh Chaturthi with Blessings', 'subtitle' => 'Pair your festive message with clean visuals and a respectful seasonal tone.', 'message' => 'Built to make your Ganesh Chaturthi campaign feel warm and premium.'],
        ],
        'dussehra' => [
            ['headline' => 'Mark Dussehra with Purpose and Pride', 'subtitle' => 'Celebrate the triumph of good with strong festive visuals and clean messaging.', 'message' => 'A bold seasonal banner designed for meaningful festive communication.'],
            ['headline' => 'Celebrate Dussehra with Festive Confidence', 'subtitle' => 'Share uplifting greetings with a polished and memorable campaign design.', 'message' => 'Made for seasonal promotions that feel strong, festive, and professional.'],
        ],
        'guru nanak jayanti' => [
            ['headline' => 'Celebrate Guru Nanak Jayanti with Grace', 'subtitle' => 'Share a message of peace, unity, and reflection through elegant festive design.', 'message' => 'A respectful seasonal banner crafted for thoughtful festive outreach.'],
            ['headline' => 'Share Peaceful Guru Nanak Jayanti Wishes', 'subtitle' => 'Honor the occasion with calm visuals and warm, polished messaging.', 'message' => 'Designed for meaningful festive greetings with a refined brand presence.'],
        ],
        'bhai dooj' => [
            ['headline' => 'Celebrate the Bond of Bhai Dooj', 'subtitle' => 'Share festive wishes that reflect love, family, and warm celebration.', 'message' => 'A cheerful seasonal banner designed for heartfelt festive communication.'],
            ['headline' => 'Send Warm Bhai Dooj Greetings', 'subtitle' => 'Bring a festive, family-focused tone to your seasonal campaign design.', 'message' => 'Made for celebratory outreach that feels warm, polished, and memorable.'],
        ],
        'default' => [
            ['headline' => 'Celebrate the Season with Your Brand', 'subtitle' => 'Share polished festive greetings with clean design and strong brand presence.', 'message' => 'A modern campaign banner built for seasonal communication and promotion.'],
            ['headline' => 'Make Your Festival Campaign Stand Out', 'subtitle' => 'Blend festive energy with elegant visuals for a more memorable message.', 'message' => 'Crafted for brand-led festive outreach across email and messaging channels.'],
        ],
    ];

    private const FESTIVAL_THEMES = [
        'diwali' => [
            'gradient' => ['#B45309', '#F59E0B', '#7C2D12'],
            'accent_bg' => '#FEF3C7',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#FFF7ED',
            'tertiary_text' => '#FDE68A',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1030, 'cy' => 148, 'r' => 120],
                'circle2' => ['cx' => 166, 'cy' => 516, 'r' => 160],
            ],
            'headline' => 'Celebrate the Festival of Lights with Joy',
            'subtitle' => 'Special Diwali offers just for you',
            'message' => 'Share happiness with your customers this Diwali season',
        ],
        'holi' => [
            'gradient' => ['#BE123C', '#E11D48', '#7C2D12', '#D946EF'],
            'accent_bg' => '#FCE7F3',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#FFF1F2',
            'tertiary_text' => '#FBCFE8',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1050, 'cy' => 120, 'r' => 140],
                'circle2' => ['cx' => 150, 'cy' => 540, 'r' => 130],
            ],
            'headline' => 'Splash Into Savings This Holi',
            'subtitle' => 'Bright deals for a colorful celebration',
            'message' => 'Make your Holi campaign vibrant and engaging',
        ],
        'christmas' => [
            'gradient' => ['#166534', '#15803D', '#991B1B'],
            'accent_bg' => '#FEF2F2',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#FEF2F2',
            'tertiary_text' => '#FECACA',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1000, 'cy' => 160, 'r' => 130],
                'circle2' => ['cx' => 200, 'cy' => 500, 'r' => 150],
            ],
            'headline' => 'Season of Giving, Season of Joy',
            'subtitle' => 'Spread warmth and cheer this holiday season',
            'message' => 'Connect with your audience through festive Christmas offers',
        ],
        'navratri' => [
            'gradient' => ['#EA580C', '#F97316', '#7C2D12'],
            'accent_bg' => '#FFF7ED',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#FFF7ED',
            'tertiary_text' => '#FED7AA',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1020, 'cy' => 140, 'r' => 125],
                'circle2' => ['cx' => 180, 'cy' => 520, 'r' => 140],
            ],
            'headline' => 'Nine Nights of Celebration',
            'subtitle' => 'Honor the divine with festive offers',
            'message' => 'Celebrate Navratri with special promotions for your customers',
        ],
        'eid' => [
            'gradient' => ['#065F46', '#047857', '#92400E'],
            'accent_bg' => '#ECFDF5',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#ECFDF5',
            'tertiary_text' => '#A7F3D0',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1040, 'cy' => 130, 'r' => 135],
                'circle2' => ['cx' => 160, 'cy' => 540, 'r' => 145],
            ],
            'headline' => 'Eid Mubarak — Celebrate with Joy',
            'subtitle' => 'Spread blessings with exclusive festive offers',
            'message' => 'Warm wishes and special deals for your customers this Eid',
        ],
        'raksha bandhan' => [
            'gradient' => ['#991B1B', '#B91C1C', '#78350F'],
            'accent_bg' => '#FEF2F2',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#FEF2F2',
            'tertiary_text' => '#FECACA',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1010, 'cy' => 150, 'r' => 120],
                'circle2' => ['cx' => 190, 'cy' => 510, 'r' => 150],
            ],
            'headline' => 'Celebrate the Bond of Love',
            'subtitle' => 'Special Raksha Bandhan offers for your loved ones',
            'message' => 'Strengthen customer relationships this Raksha Bandhan',
        ],
        'janmashtami' => [
            'gradient' => ['#1E40AF', '#2563EB', '#312E81'],
            'accent_bg' => '#EFF6FF',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#EFF6FF',
            'tertiary_text' => '#BFDBFE',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1030, 'cy' => 140, 'r' => 130],
                'circle2' => ['cx' => 170, 'cy' => 530, 'r' => 140],
            ],
            'headline' => 'Celebrate the Birth of Lord Krishna',
            'subtitle' => 'Divine blessings and festive offers await',
            'message' => 'Share joy and devotion this Janmashtami season',
        ],
        'ganesh chaturthi' => [
            'gradient' => ['#C2410C', '#EA580C', '#78350F'],
            'accent_bg' => '#FFF7ED',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#FFF7ED',
            'tertiary_text' => '#FED7AA',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1020, 'cy' => 145, 'r' => 125],
                'circle2' => ['cx' => 185, 'cy' => 525, 'r' => 145],
            ],
            'headline' => 'Welcome Lord Ganesha with Joy',
            'subtitle' => 'Blessings of wisdom and prosperity for all',
            'message' => 'Celebrate Ganesh Chaturthi with special festive offers',
        ],
        'dussehra' => [
            'gradient' => ['#B45309', '#D97706', '#78350F'],
            'accent_bg' => '#FFFBEB',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#FFFBEB',
            'tertiary_text' => '#FDE68A',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1025, 'cy' => 138, 'r' => 128],
                'circle2' => ['cx' => 175, 'cy' => 515, 'r' => 148],
            ],
            'headline' => 'Victory of Good Over Evil',
            'subtitle' => 'Celebrate Dussehra with triumphant offers',
            'message' => 'Mark the occasion with special promotions for your customers',
        ],
        'guru nanak jayanti' => [
            'gradient' => ['#D97706', '#F59E0B', '#78350F'],
            'accent_bg' => '#FEF3C7',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#FEF3C7',
            'tertiary_text' => '#FDE68A',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1040, 'cy' => 135, 'r' => 132],
                'circle2' => ['cx' => 165, 'cy' => 540, 'r' => 142],
            ],
            'headline' => 'Light of Truth and Unity',
            'subtitle' => 'Celebrate Guru Nanak\'s teachings with warmth',
            'message' => 'Spread the message of peace and unity this Gurpurab',
        ],
        'bhai dooj' => [
            'gradient' => ['#B45309', '#D97706', '#7C2D12'],
            'accent_bg' => '#FEF3C7',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#FFF7ED',
            'tertiary_text' => '#FDE68A',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1035, 'cy' => 142, 'r' => 122],
                'circle2' => ['cx' => 172, 'cy' => 522, 'r' => 152],
            ],
            'headline' => 'Celebrate the Sibling Bond',
            'subtitle' => 'Special Bhai Dooj offers for your family',
            'message' => 'Cherish relationships with warm festive greetings',
        ],
    ];

    public function __construct(
        private readonly AIBannerService $aiBannerService,
    ) {}

    public function generate(FestivalCampaign $festivalCampaign, User $user): GeneratedBanner
    {
        $useAiApi = config('campaigns.ai.banner_provider') !== null && config('campaigns.ai.banner_provider') !== 'svg';

        if ($useAiApi) {
            try {
                return $this->aiBannerService->generate($festivalCampaign, $user);
            } catch (\Throwable $e) {
                Log::warning('AI banner API failed, falling back to SVG generation', [
                    'festival_campaign_id' => $festivalCampaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->generateSvg($festivalCampaign, $user);
    }

    public function generateSvg(FestivalCampaign $festivalCampaign, User $user): GeneratedBanner
    {
        $greeting = $festivalCampaign->greetings_text ?: ('Warm wishes for ' . $festivalCampaign->festival_name);
        $brandName = $festivalCampaign->brand_name ?: ($user->company?->name ?? 'Your Brand');
        $festivalName = $festivalCampaign->festival_name;
        $festivalType = $this->getFestivalType($festivalName);
        $content = $this->buildBannerContent($festivalCampaign, $brandName);
        $theme = $this->getFestivalTheme($festivalName);
        $brandLogoPath = $festivalCampaign->brand_logo_path ?: $user->company?->logo;

        $promptText = sprintf(
            'Create a festive marketing banner for %s (%s) with greeting "%s" and brand "%s".',
            $festivalName,
            $festivalType,
            $greeting,
            $brandName
        );

        $svg = $this->renderSvgBanner(
            festivalName: $festivalName,
            greeting: $content['footer_greeting'],
            brandName: $brandName,
            headline: $content['headline'],
            subtitle: $content['subtitle'],
            message: $content['message'],
            dateLabel: $content['date_label'],
            theme: $theme,
            brandLogoPath: $brandLogoPath
        );

        $path = 'campaign-banners/company_' . $user->company_id . '/' . Str::uuid() . '.svg';
        Storage::disk('public')->put($path, $svg);

        return GeneratedBanner::create([
            'company_id' => (int) $user->company_id,
            'festival_campaign_id' => $festivalCampaign->id,
            'created_by' => $user->id,
            'source' => 'ai',
            'prompt_text' => $promptText,
            'file_path' => $path,
            'mime_type' => 'image/svg+xml',
            'width' => 1200,
            'height' => 628,
            'metadata' => [
                'festival' => $festivalName,
                'festival_type' => $festivalType,
                'greeting' => $greeting,
                'brand_name' => $brandName,
                'generated_title' => $content['headline'],
                'generated_subtitle' => $content['subtitle'],
                'generated_description' => $content['message'],
                'festival_date' => $content['date_label'],
                'theme_gradient' => $theme['gradient'],
            ],
        ]);
    }

    public function getFestivalType(string $festivalName): string
    {
        $normalized = strtolower(trim($festivalName));
        $map = [
            'diwali' => 'religious',
            'holi' => 'religious',
            'navratri' => 'religious',
            'dussehra' => 'religious',
            'janmashtami' => 'religious',
            'ganesh chaturthi' => 'religious',
            'raksha bandhan' => 'cultural',
            'bhai dooj' => 'cultural',
            'eid' => 'religious',
            'christmas' => 'religious',
            'guru nanak jayanti' => 'religious',
            'new year' => 'cultural',
            'republic day' => 'national',
            'independence day' => 'national',
        ];

        return $map[$normalized] ?? 'cultural';
    }

    public function getFestivalTheme(string $festivalName): array
    {
        $normalized = strtolower(trim($festivalName));
        $default = [
            'gradient' => ['#0F766E', '#F59E0B', '#7C2D12'],
            'accent_bg' => '#FEF3C7',
            'primary_text' => '#FFFFFF',
            'secondary_text' => '#FFF7ED',
            'tertiary_text' => '#FDE68A',
            'tag_bg' => 'rgba(255,255,255,0.18)',
            'card_bg' => 'rgba(255,255,255,0.14)',
            'decoration' => [
                'circle' => ['cx' => 1030, 'cy' => 148, 'r' => 120],
                'circle2' => ['cx' => 166, 'cy' => 516, 'r' => 160],
            ],
        ];

        $theme = self::FESTIVAL_THEMES[$normalized] ?? $default;
        $gradientVariants = $this->getThemeGradientVariants($normalized, $theme['gradient']);
        $decorationVariants = $this->getDecorationVariants($theme['decoration']);

        $theme['gradient'] = $gradientVariants[array_rand($gradientVariants)];
        $theme['decoration'] = $decorationVariants[array_rand($decorationVariants)];

        return $theme;
    }

    public function getFestivalContent(string $festivalName): array
    {
        $normalized = strtolower(trim($festivalName));

        if (isset(self::FESTIVAL_THEMES[$normalized])) {
            return [
                'headline' => self::FESTIVAL_THEMES[$normalized]['headline'],
                'subtitle' => self::FESTIVAL_THEMES[$normalized]['subtitle'],
                'message' => self::FESTIVAL_THEMES[$normalized]['message'],
            ];
        }

        return [
            'headline' => 'Celebrate ' . $festivalName . ' with Us',
            'subtitle' => 'Special offers and warm wishes for the occasion',
            'message' => 'Connect with your audience this ' . $festivalName . ' season',
        ];
    }

    private function buildBannerContent(FestivalCampaign $festivalCampaign, string $brandName): array
    {
        $festivalName = strtolower(trim($festivalCampaign->festival_name));
        $variants = self::FESTIVAL_COPY_VARIANTS[$festivalName] ?? self::FESTIVAL_COPY_VARIANTS['default'];
        $selected = $variants[array_rand($variants)];
        $greeting = trim((string) ($festivalCampaign->greetings_text ?? ''));

        $headline = $selected['headline'];
        $subtitle = $selected['subtitle'];
        $message = $selected['message'] . ' From ' . $brandName . '.';

        return [
            'headline' => $headline,
            'subtitle' => $subtitle,
            'message' => $message,
            'date_label' => $festivalCampaign->festival_date?->format('d M Y') ?? strtoupper($festivalCampaign->festival_name),
            'footer_greeting' => $greeting !== '' ? $greeting : ('Warm wishes from ' . $brandName),
        ];
    }

    private function renderSvgBanner(
        string $festivalName,
        string $greeting,
        string $brandName,
        string $headline,
        string $subtitle,
        string $message,
        string $dateLabel,
        array $theme,
        ?string $brandLogoPath = null
    ): string {
        $festivalName = e($festivalName);
        $greeting = e($greeting);
        $brandName = e($brandName);
        $headline = e($headline);
        $subtitle = e($subtitle);
        $message = e($message);
        $dateLabel = e($dateLabel);
        $logoMarkup = '';

        $gradientStops = '';
        $total = count($theme['gradient']);
        foreach ($theme['gradient'] as $i => $color) {
            $offset = $total > 1 ? ($i / ($total - 1)) * 100 : 0;
            $gradientStops .= '    <stop stop-color="' . $color . '" offset="' . $offset . '%" />' . "\n";
        }

        $dec1 = $theme['decoration']['circle'];
        $dec2 = $theme['decoration']['circle2'];

        if (filled($brandLogoPath)) {
            $logoUrl = e($this->resolveLogoSource($brandLogoPath));
            $logoMarkup = '<image href="' . $logoUrl . '" x="854" y="74" width="64" height="64" preserveAspectRatio="xMidYMid meet" clip-path="url(#logoClip)" />';
        } else {
            $initials = e(Str::upper(Str::substr(Str::of($brandName)->replaceMatches('/[^A-Za-z0-9 ]/', '')->squish()->replace(' ', '')->toString(), 0, 2) ?: 'BR'));
            $logoMarkup = '<rect x="854" y="74" width="64" height="64" rx="20" fill="rgba(255,255,255,0.18)" />'
                . '<text x="886" y="114" text-anchor="middle" fill="' . $theme['primary_text'] . '" font-family="Arial, sans-serif" font-size="24" font-weight="700">' . $initials . '</text>';
        }

        $festivalArtwork = $this->renderFestivalVisual($festivalName, $theme);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="628" viewBox="0 0 1200 628" fill="none">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1200" y2="628" gradientUnits="userSpaceOnUse">
{$gradientStops}    </linearGradient>
    <linearGradient id="textPanel" x1="72" y1="150" x2="760" y2="520" gradientUnits="userSpaceOnUse">
      <stop stop-color="rgba(15,23,42,0.28)" offset="0%" />
      <stop stop-color="rgba(15,23,42,0.18)" offset="58%" />
      <stop stop-color="rgba(15,23,42,0.04)" offset="100%" />
    </linearGradient>
    <clipPath id="logoClip">
      <rect x="854" y="74" width="64" height="64" rx="20" />
    </clipPath>
    <radialGradient id="artGlow" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(862 332) rotate(90) scale(178 178)">
      <stop stop-color="rgba(255,255,255,0.38)" />
      <stop offset="1" stop-color="rgba(255,255,255,0)" />
    </radialGradient>
  </defs>
  <rect width="1200" height="628" rx="32" fill="url(#bg)" />
  <circle cx="{$dec1['cx']}" cy="{$dec1['cy']}" r="{$dec1['r']}" fill="rgba(255,255,255,0.12)" />
  <circle cx="{$dec2['cx']}" cy="{$dec2['cy']}" r="{$dec2['r']}" fill="rgba(255,255,255,0.10)" />
  <rect x="54" y="154" width="704" height="390" rx="42" fill="url(#textPanel)" />
  <rect x="72" y="72" width="220" height="44" rx="22" fill="rgba(255,255,255,0.18)" />
  <text x="104" y="100" fill="{$theme['tertiary_text']}" font-family="Arial, sans-serif" font-size="21" font-weight="700" letter-spacing="0.4">{$dateLabel}</text>
  <rect x="72" y="132" width="312" height="50" rx="25" fill="rgba(255,255,255,0.14)" />
  <text x="104" y="165" fill="{$theme['secondary_text']}" font-family="Arial, sans-serif" font-size="24" font-weight="700">{$festivalName}</text>
  <rect x="792" y="58" width="336" height="102" rx="30" fill="rgba(255,255,255,0.16)" />
  {$logoMarkup}
  <foreignObject x="936" y="74" width="172" height="42">
    <div xmlns="http://www.w3.org/1999/xhtml" style="font-size:20px; font-weight:700; color:{$theme['primary_text']}; line-height:1.1; max-height:42px; overflow:hidden;">
        {$brandName}
    </div>
  </foreignObject>
  <text x="936" y="133" fill="{$theme['secondary_text']}" font-family="Arial, sans-serif" font-size="14" font-weight="600" letter-spacing="0.3">Festival campaign banner</text>
  <circle cx="862" cy="332" r="178" fill="url(#artGlow)" />
  {$festivalArtwork}
  <foreignObject x="72" y="212" width="650" height="176">
    <div xmlns="http://www.w3.org/1999/xhtml" style="font-size:54px; font-weight:800; color:white; line-height:1.06; max-height:176px; overflow:hidden; word-break:break-word;">
        {$headline}
    </div>
  </foreignObject>
  <foreignObject x="72" y="406" width="638" height="84">
    <div xmlns="http://www.w3.org/1999/xhtml" style="font-size:25px; font-weight:600; color:{$theme['secondary_text']}; line-height:1.34; max-height:84px; overflow:hidden; word-break:break-word;">
        {$subtitle}
    </div>
  </foreignObject>
  <rect x="72" y="502" width="676" height="94" rx="28" fill="rgba(255,255,255,0.16)" />
  <foreignObject x="98" y="520" width="624" height="46">
    <div xmlns="http://www.w3.org/1999/xhtml" style="font-size:18px; font-weight:700; color:{$theme['primary_text']}; line-height:1.3; max-height:46px; overflow:hidden; word-break:break-word;">
        {$message}
    </div>
  </foreignObject>
  <foreignObject x="98" y="564" width="624" height="20">
    <div xmlns="http://www.w3.org/1999/xhtml" style="font-size:16px; font-weight:500; color:{$theme['accent_bg']}; line-height:1.2; max-height:20px; overflow:hidden; white-space:nowrap;">
        {$greeting}
    </div>
  </foreignObject>
</svg>
SVG;
    }

    private function resolveLogoSource(string $logoPath): string
    {
        if (Str::startsWith($logoPath, ['data:', 'http://', 'https://'])) {
            return $logoPath;
        }

        if (! Storage::disk('public')->exists($logoPath)) {
            return Storage::disk('public')->url($logoPath);
        }

        $mimeType = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';
        $contents = Storage::disk('public')->get($logoPath);

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    private function getThemeGradientVariants(string $festivalName, array $fallback): array
    {
        $variants = [
            'diwali' => [
                ['#B45309', '#F59E0B', '#7C2D12'],
                ['#92400E', '#F97316', '#C2410C'],
                ['#7C2D12', '#F59E0B', '#B91C1C'],
            ],
            'holi' => [
                ['#BE123C', '#E11D48', '#D946EF'],
                ['#7C3AED', '#EC4899', '#F97316'],
                ['#2563EB', '#14B8A6', '#F43F5E'],
            ],
            'christmas' => [
                ['#166534', '#15803D', '#991B1B'],
                ['#14532D', '#16A34A', '#B91C1C'],
                ['#0F766E', '#15803D', '#7F1D1D'],
            ],
            'navratri' => [
                ['#EA580C', '#F97316', '#7C2D12'],
                ['#C2410C', '#FB923C', '#9A3412'],
                ['#B91C1C', '#F59E0B', '#7C2D12'],
            ],
            'eid' => [
                ['#065F46', '#047857', '#92400E'],
                ['#064E3B', '#059669', '#A16207'],
                ['#0F766E', '#22C55E', '#92400E'],
            ],
            'raksha bandhan' => [
                ['#991B1B', '#B91C1C', '#78350F'],
                ['#9F1239', '#DC2626', '#A16207'],
                ['#7F1D1D', '#E11D48', '#854D0E'],
            ],
            'janmashtami' => [
                ['#1E40AF', '#2563EB', '#312E81'],
                ['#1D4ED8', '#0EA5E9', '#4338CA'],
                ['#1E3A8A', '#3B82F6', '#1E1B4B'],
            ],
            'ganesh chaturthi' => [
                ['#C2410C', '#EA580C', '#78350F'],
                ['#B45309', '#F97316', '#9A3412'],
                ['#9A3412', '#FB923C', '#A16207'],
            ],
            'dussehra' => [
                ['#B45309', '#D97706', '#78350F'],
                ['#92400E', '#F59E0B', '#9A3412'],
                ['#B91C1C', '#D97706', '#7C2D12'],
            ],
            'guru nanak jayanti' => [
                ['#D97706', '#F59E0B', '#78350F'],
                ['#CA8A04', '#FBBF24', '#92400E'],
                ['#EA580C', '#F59E0B', '#854D0E'],
            ],
            'bhai dooj' => [
                ['#B45309', '#D97706', '#7C2D12'],
                ['#92400E', '#FB923C', '#7F1D1D'],
                ['#C2410C', '#F59E0B', '#78350F'],
            ],
        ];

        return $variants[$festivalName] ?? [$fallback];
    }

    private function getDecorationVariants(array $decoration): array
    {
        return [
            $decoration,
            [
                'circle' => ['cx' => $decoration['circle']['cx'] - 30, 'cy' => $decoration['circle']['cy'] + 18, 'r' => $decoration['circle']['r'] + 8],
                'circle2' => ['cx' => $decoration['circle2']['cx'] + 24, 'cy' => $decoration['circle2']['cy'] - 18, 'r' => max(110, $decoration['circle2']['r'] - 10)],
            ],
            [
                'circle' => ['cx' => $decoration['circle']['cx'] + 24, 'cy' => $decoration['circle']['cy'] - 8, 'r' => max(110, $decoration['circle']['r'] - 6)],
                'circle2' => ['cx' => $decoration['circle2']['cx'] - 18, 'cy' => $decoration['circle2']['cy'] + 20, 'r' => $decoration['circle2']['r'] + 12],
            ],
        ];
    }

    private function renderFestivalVisual(string $festivalName, array $theme): string
    {
        $imageMarkup = $this->renderFestivalImageAsset($festivalName);

        if ($imageMarkup !== null) {
            return $imageMarkup;
        }

        return $this->renderFestivalArtwork($festivalName, $theme);
    }

    private function renderFestivalImageAsset(string $festivalName): ?string
    {
        $slug = Str::slug(strtolower(trim(html_entity_decode($festivalName, ENT_QUOTES, 'UTF-8'))));

        $assetPath = $this->resolveFestivalAssetPath($slug);

        if ($assetPath === null) {
            return null;
        }

        $contents = @file_get_contents($assetPath);

        if ($contents === false) {
            return null;
        }

        $dataUri = 'data:image/png;base64,' . base64_encode($contents);

        return <<<SVG
<g transform="translate(718 182)">
  <rect x="0" y="0" width="292" height="292" rx="54" fill="rgba(255,255,255,0.08)" />
  <rect x="18" y="18" width="256" height="256" rx="48" fill="rgba(255,255,255,0.14)" stroke="rgba(255,255,255,0.18)" stroke-width="3" />
  <clipPath id="festivalPhotoClip">
    <rect x="30" y="30" width="232" height="232" rx="40" />
  </clipPath>
  <image href="{$dataUri}" x="30" y="30" width="232" height="232" preserveAspectRatio="xMidYMid slice" clip-path="url(#festivalPhotoClip)" />
</g>
SVG;
    }

    private function resolveFestivalAssetPath(string $slug): ?string
    {
        $candidates = [
            public_path('images/festival-assets/' . $slug . '.png'),
            public_path('images/festival-assets/default.png'),
            public_path('images/festival-assets/diwali.png'),
        ];

        if (isset(self::FESTIVAL_IMAGE_ASSETS[$slug])) {
            $candidates = array_merge($candidates, self::FESTIVAL_IMAGE_ASSETS[$slug]);
        } else {
            $candidates[] = self::FESTIVAL_IMAGE_ASSETS['default'][0];
        }

        foreach ($candidates as $candidate) {
            $path = Str::startsWith($candidate, ['C:/', 'C:\\', '/', '\\'])
                ? $candidate
                : base_path($candidate);

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function renderFestivalArtwork(string $festivalName, array $theme): string
    {
        $normalized = strtolower(trim(html_entity_decode($festivalName, ENT_QUOTES, 'UTF-8')));
        $stroke = $theme['primary_text'];
        $accent = $theme['tertiary_text'];
        $soft = $theme['accent_bg'];

        return match ($normalized) {
            'diwali' => <<<SVG
<g transform="translate(744 208)">
  <path d="M118 108C151 108 176 133 176 166C176 198 151 224 118 224C85 224 60 198 60 166C60 133 85 108 118 108Z" fill="rgba(255,255,255,0.14)" stroke="{$accent}" stroke-width="8"/>
  <path d="M118 24C144 52 148 78 118 102C88 78 92 52 118 24Z" fill="{$soft}" stroke="{$stroke}" stroke-width="8" stroke-linejoin="round"/>
  <path d="M78 166H158" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M58 188H178" stroke="{$stroke}" stroke-width="8" stroke-linecap="round"/>
</g>
SVG,
            'holi' => <<<SVG
<g transform="translate(728 204)">
  <circle cx="116" cy="118" r="96" fill="rgba(255,255,255,0.12)"/>
  <circle cx="76" cy="86" r="26" fill="{$accent}" />
  <circle cx="154" cy="72" r="22" fill="{$soft}" />
  <circle cx="164" cy="144" r="30" fill="rgba(255,255,255,0.28)" />
  <circle cx="88" cy="160" r="24" fill="{$accent}" opacity="0.85" />
  <path d="M48 196C92 152 142 152 186 196" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
</g>
SVG,
            'christmas' => <<<SVG
<g transform="translate(744 194)">
  <path d="M118 42L146 96H90L118 42Z" fill="{$soft}" stroke="{$stroke}" stroke-width="8" stroke-linejoin="round"/>
  <path d="M118 88L160 154H76L118 88Z" fill="rgba(255,255,255,0.16)" stroke="{$stroke}" stroke-width="8" stroke-linejoin="round"/>
  <path d="M118 138L176 222H60L118 138Z" fill="rgba(255,255,255,0.22)" stroke="{$stroke}" stroke-width="8" stroke-linejoin="round"/>
  <path d="M118 222V254" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M118 18L126 34H144L130 44L136 60L118 50L100 60L106 44L92 34H110L118 18Z" fill="{$accent}" />
</g>
SVG,
            'navratri' => <<<SVG
<g transform="translate(736 206)">
  <path d="M70 204L122 70" stroke="{$stroke}" stroke-width="12" stroke-linecap="round"/>
  <path d="M170 204L118 70" stroke="{$stroke}" stroke-width="12" stroke-linecap="round"/>
  <path d="M122 70L144 40" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M118 70L96 40" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <circle cx="122" cy="70" r="26" fill="{$soft}" stroke="{$accent}" stroke-width="8"/>
  <circle cx="70" cy="204" r="20" fill="{$accent}" />
  <circle cx="170" cy="204" r="20" fill="rgba(255,255,255,0.22)" />
</g>
SVG,
            'eid' => <<<SVG
<g transform="translate(734 196)">
  <path d="M152 70C118 48 74 70 62 112C48 160 86 214 146 212C112 226 70 206 48 172C18 126 36 58 90 36C116 26 138 32 152 44" fill="none" stroke="{$stroke}" stroke-width="12" stroke-linecap="round"/>
  <path d="M182 54L190 74H212L194 86L202 106L182 94L162 106L170 86L152 74H174L182 54Z" fill="{$accent}" />
</g>
SVG,
            'raksha bandhan', 'bhai dooj' => <<<SVG
<g transform="translate(736 204)">
  <circle cx="120" cy="126" r="62" fill="rgba(255,255,255,0.14)" stroke="{$stroke}" stroke-width="10"/>
  <circle cx="120" cy="126" r="24" fill="{$soft}" stroke="{$accent}" stroke-width="8"/>
  <path d="M28 126H58" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M182 126H212" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M120 34V64" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M120 188V218" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M58 64L78 84" stroke="{$accent}" stroke-width="8" stroke-linecap="round"/>
  <path d="M162 84L182 64" stroke="{$accent}" stroke-width="8" stroke-linecap="round"/>
  <path d="M58 188L78 168" stroke="{$accent}" stroke-width="8" stroke-linecap="round"/>
  <path d="M162 168L182 188" stroke="{$accent}" stroke-width="8" stroke-linecap="round"/>
</g>
SVG,
            'janmashtami' => <<<SVG
<g transform="translate(722 214)">
  <path d="M52 146C108 118 160 118 216 146" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M78 124V164" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M192 124V164" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M134 42C172 64 174 114 138 134C112 114 112 74 134 42Z" fill="{$soft}" stroke="{$accent}" stroke-width="8"/>
  <path d="M134 52C118 74 118 100 136 122" stroke="{$stroke}" stroke-width="6" stroke-linecap="round"/>
</g>
SVG,
            'ganesh chaturthi' => <<<SVG
<g transform="translate(730 198)">
  <path d="M126 46C168 46 196 74 196 116C196 146 176 170 148 178C150 202 136 224 108 228C118 212 122 194 118 174C92 168 74 146 74 118C74 76 98 46 126 46Z" fill="rgba(255,255,255,0.14)" stroke="{$stroke}" stroke-width="10" stroke-linejoin="round"/>
  <path d="M130 126C126 148 138 168 160 178" stroke="{$accent}" stroke-width="10" stroke-linecap="round"/>
  <circle cx="108" cy="106" r="8" fill="{$soft}" />
</g>
SVG,
            'dussehra' => <<<SVG
<g transform="translate(730 202)">
  <path d="M70 198C132 140 132 88 70 30" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M70 198C138 150 190 116 210 108" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
  <path d="M174 86L216 108L174 130" fill="none" stroke="{$accent}" stroke-width="10" stroke-linejoin="round"/>
  <path d="M92 60C118 84 118 142 92 166" stroke="{$accent}" stroke-width="8" stroke-linecap="round"/>
</g>
SVG,
            'guru nanak jayanti' => <<<SVG
<g transform="translate(736 204)">
  <circle cx="120" cy="126" r="72" fill="rgba(255,255,255,0.14)" stroke="{$stroke}" stroke-width="10"/>
  <path d="M120 72V182" stroke="{$accent}" stroke-width="10" stroke-linecap="round"/>
  <path d="M82 102C96 84 110 76 120 76C144 76 162 96 162 120C162 148 142 176 102 176" fill="none" stroke="{$stroke}" stroke-width="10" stroke-linecap="round"/>
</g>
SVG,
            default => <<<SVG
<g transform="translate(736 204)">
  <circle cx="120" cy="126" r="88" fill="rgba(255,255,255,0.14)" stroke="{$stroke}" stroke-width="10"/>
  <circle cx="120" cy="126" r="30" fill="{$soft}" />
  <path d="M120 44V76" stroke="{$accent}" stroke-width="8" stroke-linecap="round"/>
  <path d="M120 176V208" stroke="{$accent}" stroke-width="8" stroke-linecap="round"/>
  <path d="M38 126H70" stroke="{$accent}" stroke-width="8" stroke-linecap="round"/>
  <path d="M170 126H202" stroke="{$accent}" stroke-width="8" stroke-linecap="round"/>
</g>
SVG,
        };
    }
}
