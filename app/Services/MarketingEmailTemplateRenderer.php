<?php

namespace App\Services;

class MarketingEmailTemplateRenderer
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function render(array $blocks, ?string $preheader = null): string
    {
        $sections = [];

        if (filled($preheader)) {
            $sections[] = sprintf(
                '<div style="font-size:13px;color:#64748b;margin-bottom:12px;">%s</div>',
                e((string) $preheader)
            );
        }

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? 'text');

            if ($type === 'image') {
                $alignment = match ((string) ($block['align'] ?? 'center')) {
                    'left' => 'left',
                    'right' => 'right',
                    default => 'center',
                };
                $width = max(10, min(100, (int) ($block['width'] ?? 100)));
                $height = max(80, min(480, (int) ($block['height'] ?? 240)));
                $sections[] = sprintf(
                    '<div style="margin:0 0 18px;text-align:%s;"><img src="%s" alt="%s" style="display:inline-block;width:%d%%;height:%dpx;object-fit:cover;border-radius:18px;"></div>',
                    $alignment,
                    e((string) ($block['url'] ?? '')),
                    e((string) ($block['alt'] ?? '')),
                    $width,
                    $height
                );
                continue;
            }

            if ($type === 'button' || $type === 'custom_button') {
                $sections[] = sprintf(
                    '<div style="margin:0 0 18px;text-align:center;"><a href="%s" style="display:inline-block;padding:12px 20px;border-radius:12px;background:%s;color:%s;font-size:14px;font-weight:700;text-decoration:none;">%s</a></div>',
                    e((string) ($block['url'] ?? '#')),
                    e((string) ($block['bg'] ?? '#0f766e')),
                    e((string) ($block['color'] ?? '#ffffff')),
                    e((string) ($block['text'] ?? 'Click Here'))
                );
                continue;
            }

            if ($type === 'button_group') {
                $buttons = collect($block['buttons'] ?? [])
                    ->filter(fn ($button) => filled($button['text'] ?? null))
                    ->take(2)
                    ->map(function ($button) {
                        return sprintf(
                            '<a href="%s" style="display:inline-block;padding:12px 18px;border-radius:12px;background:%s;color:%s;font-size:14px;font-weight:700;text-decoration:none;margin:0 6px 8px;">%s</a>',
                            e((string) ($button['url'] ?? '#')),
                            e((string) ($button['bg'] ?? '#0f766e')),
                            e((string) ($button['color'] ?? '#ffffff')),
                            e((string) ($button['text'] ?? 'Learn More'))
                        );
                    })
                    ->implode('');

                $sections[] = '<div style="margin:0 0 18px;text-align:center;">' . $buttons . '</div>';

                continue;
            }

            if ($type === 'topics' || $type === 'topics_extra') {
                $title = filled($block['title'] ?? null)
                    ? sprintf(
                        '<div style="font-size:16px;font-weight:700;color:#0f172a;margin:0 0 10px;">%s</div>',
                        e((string) $block['title'])
                    )
                    : '';

                $description = filled($block['description'] ?? null)
                    ? sprintf(
                        '<div style="font-size:14px;line-height:1.7;color:#475569;margin:0 0 12px;">%s</div>',
                        nl2br(e((string) $block['description']), false)
                    )
                    : '';

                $items = collect($block['items'] ?? [])
                    ->filter(fn ($item) => filled($item))
                    ->map(fn ($item) => sprintf(
                        '<li style="margin:0 0 8px;color:#0f172a;">%s</li>',
                        e((string) $item)
                    ))
                    ->implode('');

                $sections[] = sprintf(
                    '<div style="margin:0 0 18px;padding:18px 20px;border-radius:18px;background:#f8fafc;border:1px solid #e2e8f0;">%s%s<ul style="padding-left:18px;margin:0;">%s</ul></div>',
                    $title,
                    $description,
                    $items
                );

                continue;
            }

            if ($type === 'divider') {
                $sections[] = sprintf(
                    '<hr style="border:none;border-top:%dpx solid %s;margin:0 0 18px;">',
                    max(1, (int) ($block['weight'] ?? 1)),
                    e((string) ($block['color'] ?? '#e2e8f0'))
                );
                continue;
            }

            if ($type === 'spacer') {
                $sections[] = sprintf(
                    '<div style="height:%dpx;"></div>',
                    max(4, (int) ($block['height'] ?? 24))
                );
                continue;
            }

            $content = $this->sanitizeRichText((string) ($block['content'] ?? ''));
            $sections[] = sprintf(
                '<div style="margin:0 0 18px;color:#0f172a;font-size:15px;line-height:1.8;">%s</div>',
                $content
            );
        }

        return implode("\n", [
            '<!doctype html>',
            '<html>',
            '<body style="margin:0;padding:24px;background:#f8fafc;font-family:Inter,Arial,sans-serif;">',
            '  <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;padding:24px;">',
            implode("\n", $sections),
            '  </div>',
            '</body>',
            '</html>',
        ]);
    }

    private function sanitizeRichText(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        if (! str_contains($html, '<')) {
            return nl2br(e($html), false);
        }

        $allowedTags = '<p><br><strong><b><em><i><u><h1><h2><h3><ul><ol><li><a><span><div>';
        $html = strip_tags($html, $allowedTags);

        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? '';
        $html = preg_replace('/\son\w+="[^"]*"/i', '', $html) ?? '';
        $html = preg_replace("/\son\w+='[^']*'/i", '', $html) ?? '';
        $html = preg_replace('/javascript:/i', '', $html) ?? '';

        return $html;
    }
}
