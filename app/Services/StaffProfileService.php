<?php

namespace App\Services;

use App\Models\Amc;
use App\Models\Staff;
use App\Models\User;
use App\Support\StaffMobileTokenStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class StaffProfileService
{
    public function __construct(
        private readonly MediaUploadService $mediaUploadService,
        private readonly StaffMobileTokenStore $tokenStore,
    ) {
    }

    public function dashboardStatistics(Staff $staff): array
    {
        $staff->loadMissing(['user', 'role']);

        $companyId = (int) $staff->company_id;
        $staffUserId = (int) $staff->user_id;
        $staffId = (int) $staff->id;
        $today = Carbon::today()->toDateString();

        $ticketStats = \App\Models\Ticket::query()
            ->where('company_id', $companyId)
            ->where('assigned_to', $staffUserId)
            ->selectRaw(
                'COUNT(*) as total_assigned_tickets,' .
                'SUM(CASE WHEN DATE(visit_date) = ? THEN 1 ELSE 0 END) as today_tickets,' .
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_tickets,' .
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_tickets,' .
                'SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as closed_tickets,' .
                'SUM(CASE WHEN repeat_status = 1 THEN 1 ELSE 0 END) as repeat_tickets',
                [
                    $today,
                    'open',
                    'in_progress',
                    'resolved',
                    'closed',
                ]
            )
            ->first();

        $amcStats = Amc::query()
            ->where('company_id', $companyId)
            ->where('assigned_staff_id', $staffId)
            ->selectRaw(
                'COUNT(*) as total_assigned_amc,' .
                'SUM(CASE WHEN start_date <= ? AND end_date >= ? THEN 1 ELSE 0 END) as pending_amc,' .
                'SUM(CASE WHEN end_date < ? THEN 1 ELSE 0 END) as completed_amc,' .
                'SUM(CASE WHEN start_date > ? THEN 1 ELSE 0 END) as upcoming_amc',
                [
                    $today,
                    $today,
                    $today,
                    $today,
                ]
            )
            ->first();

        return [
            'today_tickets' => (int) ($ticketStats->today_tickets ?? 0),
            'open_tickets' => (int) ($ticketStats->open_tickets ?? 0),
            'in_progress_tickets' => (int) ($ticketStats->in_progress_tickets ?? 0),
            'closed_tickets' => (int) ($ticketStats->closed_tickets ?? 0),
            'repeat_tickets' => (int) ($ticketStats->repeat_tickets ?? 0),
            'pending_amc' => (int) ($amcStats->pending_amc ?? 0),
            'completed_amc' => (int) ($amcStats->completed_amc ?? 0),
            'upcoming_amc' => (int) ($amcStats->upcoming_amc ?? 0),
            'total_assigned_tickets' => (int) ($ticketStats->total_assigned_tickets ?? 0),
            'total_assigned_amc' => (int) ($amcStats->total_assigned_amc ?? 0),
        ];
    }

    public function updateProfile(User $user, array $validated, ?UploadedFile $profileImage = null): Staff
    {
        $staff = $this->resolveStaff($user);
        $newProfilePhotoPath = null;
        $oldProfilePhotoPath = $staff->profile_photo;

        try {
            if ($profileImage) {
                $newProfilePhotoPath = $this->mediaUploadService->store(
                    $profileImage,
                    'company_' . $user->company_id . '/staff_profiles',
                    'public'
                );
            }

            DB::transaction(function () use ($staff, $user, $validated, $newProfilePhotoPath): void {
                $name = trim((string) $validated['name']);
                $currentValues = [
                    'contact_number' => $staff->contact_number,
                    'address' => $staff->address,
                    'city' => $staff->city,
                    'state' => $staff->state,
                    'country' => $staff->country,
                    'zipcode' => $staff->zipcode,
                ];

                $staff->forceFill([
                    'name' => $name,
                    'contact_number' => array_key_exists('phone', $validated) ? $validated['phone'] : $currentValues['contact_number'],
                    'address' => array_key_exists('address', $validated) ? $validated['address'] : $currentValues['address'],
                    'city' => array_key_exists('city', $validated) ? $validated['city'] : $currentValues['city'],
                    'state' => array_key_exists('state', $validated) ? $validated['state'] : $currentValues['state'],
                    'country' => array_key_exists('country', $validated) ? $validated['country'] : $currentValues['country'],
                    'zipcode' => array_key_exists('zipcode', $validated) ? $validated['zipcode'] : $currentValues['zipcode'],
                ]);

                if ($newProfilePhotoPath) {
                    $staff->profile_photo = $newProfilePhotoPath;
                }

                $staff->save();

                $user->forceFill([
                    'name' => $name,
                ])->save();
            });

            if ($newProfilePhotoPath && filled($oldProfilePhotoPath) && $oldProfilePhotoPath !== $newProfilePhotoPath) {
                $this->mediaUploadService->delete($oldProfilePhotoPath, 'public', (int) $user->company_id);
            }
        } catch (Throwable $throwable) {
            if ($newProfilePhotoPath) {
                $this->mediaUploadService->delete($newProfilePhotoPath, 'public', (int) $user->company_id);
            }

            throw $throwable;
        }

        return $staff->fresh(['user.company', 'role']) ?? $staff;
    }

    public function changePassword(User $user, string $newPassword): void
    {
        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->save();

        $this->tokenStore->forgetAllForUser($user);
    }

    public function resolveStaff(User $user): Staff
    {
        $staff = $user->staff()->with(['user.company', 'role'])->first();

        if (! $staff || (int) $staff->user_id !== (int) $user->id) {
            throw new HttpResponseException(response()->json([
                'message' => 'Profile not found.',
            ], 404));
        }

        return $staff;
    }
}
