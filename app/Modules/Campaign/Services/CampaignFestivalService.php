<?php

namespace App\Modules\Campaign\Services;

use App\Models\FestivalCampaign;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CampaignFestivalService
{
    public function upcomingFestivals(): array
    {
        $provider = config('campaigns.ai.provider', 'openai');

        return match ($provider) {
            'gemini' => $this->fetchFromGemini(),
            'openai' => $this->fetchFromOpenAi(),
            default => $this->fallbackFestivals(),
        };
    }

    public function storeSelection(User $user, array $festival, array $attributes = []): FestivalCampaign
    {
        return FestivalCampaign::create([
            'company_id' => (int) $user->company_id,
            'selected_by_user_id' => $user->id,
            'festival_name' => $festival['festival_name'],
            'festival_date' => $festival['date'],
            'description' => $festival['description'] ?? null,
            'greetings_text' => $attributes['greetings_text'] ?? ('Happy ' . $festival['festival_name']),
            'brand_name' => $attributes['brand_name'] ?? $user->company?->name,
            'brand_logo_path' => $attributes['brand_logo_path'] ?? null,
            'ai_provider' => config('campaigns.ai.provider'),
            'prompt_payload' => [
                'festival' => $festival,
                'attributes' => $attributes,
                'festival_type' => $this->getFestivalType($festival['festival_name']),
            ],
        ]);
    }

    private function fetchFromOpenAi(): array
    {
        $apiKey = config('campaigns.ai.openai.api_key');

        if (! filled($apiKey)) {
            return $this->fallbackFestivals();
        }

        try {
            $response = Http::withToken($apiKey)
                ->baseUrl((string) config('campaigns.ai.openai.base_url'))
                ->timeout(20)
                ->post('/responses', [
                    'model' => config('campaigns.ai.openai.model'),
                    'input' => [[
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => 'List all major upcoming Indian festivals from today through the next 18 months in JSON array format with keys festival_name, date, description, and festival_type. Include Hindu, Muslim, Sikh, Christian, Buddhist, Jain, national, regional, and cultural festivals celebrated in India. Use YYYY-MM-DD dates only.',
                        ]],
                    ]],
                ]);

            $text = (string) data_get($response->json(), 'output.0.content.0.text', '');

            return $this->normalizeFestivalPayload($text);
        } catch (\Throwable) {
            return $this->fallbackFestivals();
        }
    }

    private function fetchFromGemini(): array
    {
        $apiKey = config('campaigns.ai.gemini.api_key');

        if (! filled($apiKey)) {
            return $this->fallbackFestivals();
        }

        try {
            $response = Http::timeout(20)
                ->post(rtrim((string) config('campaigns.ai.gemini.base_url'), '/') . '/models/' . config('campaigns.ai.gemini.model') . ':generateContent?key=' . $apiKey, [
                    'contents' => [[
                        'parts' => [[
                            'text' => 'List all major upcoming Indian festivals from today through the next 18 months in JSON array format with keys festival_name, date, description, and festival_type. Include Hindu, Muslim, Sikh, Christian, Buddhist, Jain, national, regional, and cultural festivals celebrated in India. Use YYYY-MM-DD dates only.',
                        ]],
                    ]],
                ]);

            $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');

            return $this->normalizeFestivalPayload($text);
        } catch (\Throwable) {
            return $this->fallbackFestivals();
        }
    }

    private function normalizeFestivalPayload(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```json|```$/m', '', $text) ?: $text;
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            return $this->fallbackFestivals();
        }

        $festivalTypeMap = [
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
        ];

        return collect($decoded)
            ->filter(fn ($item) => is_array($item) && filled($item['festival_name'] ?? null) && filled($item['date'] ?? null))
            ->map(function (array $item) use ($festivalTypeMap) {
                $normalized = strtolower(trim($item['festival_name']));

                return [
                    'festival_name' => (string) $item['festival_name'],
                    'date' => Carbon::parse($item['date'])->toDateString(),
                    'description' => Str::limit((string) ($item['description'] ?? ''), 300),
                    'festival_type' => $festivalTypeMap[$normalized] ?? ($item['festival_type'] ?? 'cultural'),
                ];
            })
            ->values()
            ->all() ?: $this->fallbackFestivals();
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
        ];

        return $map[$normalized] ?? 'cultural';
    }

    private function fallbackFestivals(): array
    {
        $year = now()->year;

        return [
            ['festival_name' => 'Raksha Bandhan', 'date' => Carbon::create($year, 8, 28)->toDateString(), 'description' => 'Celebrates the bond between siblings with rituals and gifts.', 'festival_type' => 'cultural'],
            ['festival_name' => 'Janmashtami', 'date' => Carbon::create($year, 9, 6)->toDateString(), 'description' => 'Honors the birth of Lord Krishna with prayers and festive decorations.', 'festival_type' => 'religious'],
            ['festival_name' => 'Ganesh Chaturthi', 'date' => Carbon::create($year, 9, 18)->toDateString(), 'description' => 'Welcomes Lord Ganesha with community celebrations and prayers.', 'festival_type' => 'religious'],
            ['festival_name' => 'Navratri', 'date' => Carbon::create($year, 10, 3)->toDateString(), 'description' => 'Nine nights of devotion, dance, and traditional cultural celebrations.', 'festival_type' => 'religious'],
            ['festival_name' => 'Dussehra', 'date' => Carbon::create($year, 10, 12)->toDateString(), 'description' => 'Marks the victory of good over evil across India.', 'festival_type' => 'religious'],
            ['festival_name' => 'Diwali', 'date' => Carbon::create($year, 10, 20)->toDateString(), 'description' => 'Festival of lights celebrated with joy, gratitude, and prosperity wishes.', 'festival_type' => 'religious'],
            ['festival_name' => 'Bhai Dooj', 'date' => Carbon::create($year, 10, 23)->toDateString(), 'description' => 'Celebrates sibling bonds with blessings and family gatherings.', 'festival_type' => 'cultural'],
            ['festival_name' => 'Guru Nanak Jayanti', 'date' => Carbon::create($year, 11, 5)->toDateString(), 'description' => 'Commemorates Guru Nanak with devotion and community service.', 'festival_type' => 'religious'],
            ['festival_name' => 'Christmas', 'date' => Carbon::create($year, 12, 25)->toDateString(), 'description' => 'A festive season of celebration, joy, and goodwill.', 'festival_type' => 'religious'],
        ];
    }
}
