<?php

namespace App\Console\Commands;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:enforce-abandonment-policy')]
#[Description('Apply the three-strike abandonment policy to unclaimed ready-for-pickup documents.')]
class EnforceAbandonmentPolicy extends Command
{
    /**
     * Abandon a document that has been ready for pickup for at least this many working days.
     */
    private const READY_DAYS_THRESHOLD = 7;

    public function handle(): int
    {
        $today = now()->startOfDay();
        $readyBefore = $today->subWeekdays(self::READY_DAYS_THRESHOLD - 1)->endOfDay();

        $abandoned = DocumentRequest::query()
            ->where('status', DocumentRequest::STATUS_READY)
            ->where('ready_at', '<=', $readyBefore)
            ->update(['status' => DocumentRequest::STATUS_ABANDONED]);

        if ($abandoned > 0) {
            $userIds = DocumentRequest::where('status', DocumentRequest::STATUS_ABANDONED)
                ->pluck('user_id')
                ->unique();

            foreach ($userIds as $userId) {
                $user = User::find($userId);

                if ($user === null) {
                    continue;
                }

                $strikeCount = DocumentRequest::where('user_id', $user->id)
                    ->where('status', DocumentRequest::STATUS_ABANDONED)
                    ->count();

                $user->update([
                    'abandoned_count' => $strikeCount,
                    'restricted_until' => $strikeCount >= 3 ? now()->addDays(30) : $user->restricted_until,
                ]);
            }
        }

        $this->info("Scanned abandons: {$abandoned} document(s) marked as abandoned.");

        $restricted = User::where('restricted_until', '>', now())->count();
        $this->info("Users currently restricted: {$restricted}.");

        return self::SUCCESS;
    }
}
