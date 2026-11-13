<?php

namespace App\Services;

use App\Models\FestivalCampaign;
use App\Models\GeneratedBanner;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AIBannerService
{
    private function getFestivalType(string $festivalName): string
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

    private function getCulturalContext(string $festivalName): string
    {
        $contexts = [
            'diwali' => 'Festival of Lights — celebrates prosperity, happiness, and the victory of light over darkness. Use warm golds, deep reds, saffron, and lamps/diya motifs.',
            'holi' => 'Festival of Colors — celebrates spring, love, and the triumph of good over evil. Use vibrant pinks, oranges, blues, greens, and color splash motifs.',
            'christmas' => 'Celebrates the birth of Jesus Christ. A season of giving, joy, and family gatherings. Use reds, greens, golds, and star/snowflake motifs.',
            'navratri' => 'Nine nights of devotion to Goddess Durga. Celebrated with dance (garba/dandiya) and cultural performances. Use vibrant oranges, reds, and traditional patterns.',
            'eid' => 'Marks the end of Ramadan. A festival of charity, gratitude, and community. Use greens, golds, and crescent moon motifs.',
            'raksha bandhan' => 'Celebrates the bond between siblings. Sisters tie sacred threads (rakhi) on brothers\' wrists. Use reds, golds, and intricate thread patterns.',
            'janmashtami' => 'Celebrates the birth of Lord Krishna. Use blues, yellows, and peacock feather motifs.',
            'ganesh chaturthi' => 'Welcomes Lord Ganesha — the remover of obstacles. Use reds, golds, oranges, and modak/elephant motifs.',
            'dussehra' => 'Marks Lord Rama\'s victory over Ravana — triumph of good over evil. Use reds, golds, and bow/arrow motifs.',
            'guru nanak jayanti' => 'Celebrates the birth of Guru Nanak, the first Sikh guru. A time for prayer, community service, and reflection. Use oranges, whites, blues, and Ik Onkar motifs.',
            'bhai dooj' => 'Celebrates the bond between brothers and sisters, similar to Raksha Bandhan. Use warm reds, golds, and tilak motifs.',
        ];

        $normalized = strtolower(trim($festivalName));

        return $contexts[$normalized] ?? 'A festive Indian celebration with traditional colors and decorations.';
    }

    private function getStyleDirective(string $festivalName): string
    {
        $styles = [
            'diwali' => 'Elegant and warm with golden accents, diya lamps, and shimmering lights. Luxurious and premium feel.',
            'holi' => 'Vibrant, energetic, and playful with color splashes, abstract patterns, and joyful imagery.',
            'christmas' => 'Cozy, warm, and magical with evergreen elements, stars, and soft glowing lights.',
            'navratri' => 'Energetic and cultural with garba/dandiya sticks, traditional attire, and bright celebratory colors.',
            'eid' => 'Peaceful and elegant with crescent moons, lanterns, and intricate geometric patterns.',
            'raksha bandhan' => 'Warm and emotional with rakhi threads, intricate patterns, and familial warmth.',
            'janmashtami' => 'Divine and serene with peacock feathers, flutes, and blue-tinted spiritual imagery.',
            'ganesh chaturthi' => 'Devotional and vibrant with modaks, traditional ornaments, and festive energy.',
            'dussehra' => 'Triumphant and powerful with Ram-Darbar imagery, firecrackers, and victory motifs.',
            'guru nanak jayanti' => 'Peaceful and spiritual with divine light, community gatherings, and serene imagery.',
            'bhai dooj' => 'Warm and familial with tilak ceremonies, sweets, and sibling bonding imagery.',
        ];

        $normalized = strtolower(trim($festivalName));

        return $styles[$normalized] ?? 'Festive, elegant, and modern with traditional Indian design elements.';
    }

    public function generate(FestivalCampaign $festivalCampaign, User $user): GeneratedBanner
    {
        $provider = config('campaigns.ai.banner_provider', 'openai');

        return match ($provider) {
            'openai' => $this->generateViaOpenAi($festivalCampaign, $user),
            'stability' => $this->generateViaStability($festivalCampaign, $user),
            default => $this->generateViaOpenAi($festivalCampaign, $user),
        };
    }

    private function generateViaOpenAi(FestivalCampaign $festivalCampaign, User $user): GeneratedBanner
    {
        $apiKey = config('campaigns.ai.openai.api_key');
        $festivalName = $festivalCampaign->festival_name;
        $greeting = $festivalCampaign->greetings_text ?: "Happy {$festivalName}";
        $brandName = $festivalCampaign->brand_name ?: ($user->company?->name ?? 'Your Brand');
        $festivalType = $this->getFestivalType($festivalName);
        $culturalContext = $this->getCulturalContext($festivalName);
        $style = $this->getStyleDirective($festivalName);
        $description = $festivalCampaign->description ?? "Celebrate {$festivalName} with your audience";

        $prompt = sprintf(
            'Create a unique, professional marketing banner image for %s festival (type: %s). Cultural context: %s. Style guidance: %s. Include the greeting "%s" and brand name "%s" prominently. Generate a banner with: 1) A festival-specific headline that is unique and contextual 2) A context-aware subtitle 3) A short promotional message tailored to this festival. DO NOT use generic phrases like "Celebrate with your audience" or "Email + WhatsApp ready". Make every element specific to %s. Style: elegant, modern, suitable for email and WhatsApp marketing. Dimensions: 1200x628 pixels. Description: %s',
            $festivalName,
            $festivalType,
            $culturalContext,
            $style,
            $greeting,
            $brandName,
            $festivalName,
            $description
        );

        try {
            $response = Http::withToken($apiKey)
                ->baseUrl((string) config('campaigns.ai.openai.base_url'))
                ->timeout(60)
                ->post('/images/generations', [
                    'model' => 'dall-e-3',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1792x1024',
                    'quality' => 'standard',
                    'response_format' => 'b64_json',
                ]);

            $b64 = data_get($response->json(), 'data.0.b64_json');

            if (! $b64) {
                throw new \RuntimeException('No image data returned from OpenAI: ' . ($response->body() ?: 'empty response'));
            }

            return $this->storeBanner($b64, $festivalCampaign, $user, $prompt, 'openai/dall-e-3');
        } catch (\Throwable $e) {
            Log::error('AI Banner generation via OpenAI failed', [
                'festival_campaign_id' => $festivalCampaign->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function generateViaStability(FestivalCampaign $festivalCampaign, User $user): GeneratedBanner
    {
        $apiKey = config('campaigns.ai.stability.api_key');
        $festivalName = $festivalCampaign->festival_name;
        $greeting = $festivalCampaign->greetings_text ?: "Happy {$festivalName}";
        $brandName = $festivalCampaign->brand_name ?: ($user->company?->name ?? 'Your Brand');
        $festivalType = $this->getFestivalType($festivalName);
        $culturalContext = $this->getCulturalContext($festivalName);
        $style = $this->getStyleDirective($festivalName);

        $prompt = sprintf(
            'Unique festival marketing banner for %s (%s). Context: %s. Style: %s. Include "%s" and "%s". Festival-specific headline and messaging. No generic marketing phrases. High quality, professional, suitable for email marketing.',
            $festivalName,
            $festivalType,
            $culturalContext,
            $style,
            $greeting,
            $brandName
        );

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image', [
                    'text_prompts' => [['text' => $prompt, 'weight' => 1]],
                    'cfg_scale' => 7,
                    'height' => 1024,
                    'width' => 1024,
                    'samples' => 1,
                    'steps' => 30,
                ]);

            $b64 = data_get($response->json(), 'artifacts.0.base64');

            if (! $b64) {
                throw new \RuntimeException('No image data returned from Stability AI');
            }

            return $this->storeBanner($b64, $festivalCampaign, $user, $prompt, 'stability/sd-xl');
        } catch (\Throwable $e) {
            Log::error('AI Banner generation via Stability AI failed', [
                'festival_campaign_id' => $festivalCampaign->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function storeBanner(string $base64, FestivalCampaign $festivalCampaign, User $user, string $prompt, string $source): GeneratedBanner
    {
        $binary = base64_decode($base64);
        $path = 'campaign-banners/company_' . $user->company_id . '/' . Str::uuid() . '.png';
        Storage::disk('public')->put($path, $binary);

        return GeneratedBanner::create([
            'company_id' => (int) $user->company_id,
            'festival_campaign_id' => $festivalCampaign->id,
            'created_by' => $user->id,
            'source' => 'ai_image_api',
            'prompt_text' => $prompt,
            'file_path' => $path,
            'mime_type' => 'image/png',
            'width' => 1792,
            'height' => 1024,
            'metadata' => [
                'provider' => $source,
                'festival' => $festivalCampaign->festival_name,
                'festival_type' => $this->getFestivalType($festivalCampaign->festival_name),
                'greeting' => $festivalCampaign->greetings_text,
                'brand_name' => $festivalCampaign->brand_name,
                'generated_title' => 'AI-generated banner for ' . $festivalCampaign->festival_name,
                'generated_subtitle' => $festivalCampaign->greetings_text ?: 'Festive greetings from our team',
                'generated_description' => $festivalCampaign->description ?: ('Celebrate ' . $festivalCampaign->festival_name . ' with special offers'),
            ],
        ]);
    }
}
