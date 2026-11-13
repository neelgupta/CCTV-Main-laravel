<?php

namespace App\Modules\Campaign\Services;

use App\Models\Campaign;
use App\Models\EmailBounce;
use App\Models\EmailClick;
use App\Models\EmailLog;
use App\Models\EmailOpen;
use App\Models\WhatsAppLog;
use Illuminate\Support\Facades\DB;

class CampaignAnalyticsService
{
    public function dashboard(int $companyId): array
    {
        $campaigns = Campaign::withoutGlobalScopes()->where('company_id', $companyId);

        $totalCampaigns = (clone $campaigns)->count();
        $activeCampaigns = (clone $campaigns)->where('status', Campaign::STATUS_RUNNING)->count();
        $scheduledCampaigns = (clone $campaigns)->where('status', Campaign::STATUS_SCHEDULED)->count();
        $completedCampaigns = (clone $campaigns)->where('status', Campaign::STATUS_COMPLETED)->count();

        $emailLogs = EmailLog::query()->where('company_id', $companyId);
        $sent = (clone $emailLogs)->whereIn('status', ['sent', 'delivered', 'opened', 'clicked', 'bounced'])->count();
        $delivered = (clone $emailLogs)->whereIn('status', ['delivered', 'opened', 'clicked'])->count();
        $opened = EmailOpen::query()
            ->whereIn('email_log_id', EmailLog::query()->where('company_id', $companyId)->select('id'))
            ->distinct('email_log_id')
            ->count('email_log_id');
        $clicked = EmailClick::query()
            ->whereIn('email_log_id', EmailLog::query()->where('company_id', $companyId)->select('id'))
            ->distinct('email_log_id')
            ->count('email_log_id');
        $bounced = EmailBounce::query()
            ->whereIn('email_log_id', EmailLog::query()->where('company_id', $companyId)->select('id'))
            ->count();

        $whatsAppStats = WhatsAppLog::query()
            ->where('company_id', $companyId)
            ->selectRaw("
                SUM(CASE WHEN status IN ('sent','delivered','read') THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
            ")
            ->first();

        $openRate = $sent > 0 ? round(($opened / $sent) * 100, 2) : 0.0;
        $ctr = $sent > 0 ? round(($clicked / $sent) * 100, 2) : 0.0;
        $bounceRate = $sent > 0 ? round(($bounced / $sent) * 100, 2) : 0.0;

        $emailVsWhatsapp = Campaign::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->selectRaw("
                DATE(created_at) as day,
                SUM(sent_count) as email_sent,
                SUM(delivered_count) as delivered,
                SUM(opened_count) as opened,
                SUM(clicked_count) as clicked
            ")
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->limit(14)
            ->get();

        $festivalPerformance = Campaign::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', Campaign::TYPE_FESTIVAL)
            ->latest()
            ->limit(10)
            ->get(['name', 'sent_count', 'opened_count', 'clicked_count', 'delivered_count']);

        return [
            'cards' => [
                'total_campaigns' => $totalCampaigns,
                'active_campaigns' => $activeCampaigns,
                'scheduled_campaigns' => $scheduledCampaigns,
                'completed_campaigns' => $completedCampaigns,
            ],
            'email' => [
                'total_sent' => $sent,
                'delivered' => $delivered,
                'opened' => $opened,
                'clicked' => $clicked,
                'bounced' => $bounced,
                'open_rate' => $openRate,
                'ctr' => $ctr,
                'bounce_rate' => $bounceRate,
            ],
            'whatsapp' => [
                'sent' => (int) ($whatsAppStats->sent_count ?? 0),
                'delivered' => (int) ($whatsAppStats->delivered_count ?? 0),
                'read' => (int) ($whatsAppStats->read_count ?? 0),
                'failed' => (int) ($whatsAppStats->failed_count ?? 0),
            ],
            'charts' => [
                'email_vs_whatsapp' => $emailVsWhatsapp,
                'festival_performance' => $festivalPerformance,
            ],
        ];
    }
}
