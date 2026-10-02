<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Mail\BillingReminderMail;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Dunning: sends renewal reminders before ends_at and overdue notices after.
 * Runs daily; each subscription is reminded at most once per day.
 */
class BillingRemind extends Command
{
    protected $signature = 'billing:remind';

    protected $description = 'Send subscription renewal / overdue reminders';

    public function handle(): int
    {
        $reminderDays = (array) config('manager.reminder_days', [7, 3, 1]);
        $overdueWindow = (int) config('manager.overdue_reminder_days', 14);
        $sent = 0;

        // Flip overdue actives to past_due first.
        Subscription::query()
            ->where('ends_at', '<', now())
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->update(['status' => SubscriptionStatus::PAST_DUE->value]);

        Subscription::query()
            ->with(['siteOwner', 'plan'])
            ->whereNotNull('ends_at')
            ->whereIn('status', [
                SubscriptionStatus::ACTIVE->value,
                SubscriptionStatus::TRIALING->value,
                SubscriptionStatus::PAST_DUE->value,
            ])
            ->chunkById(100, function ($subscriptions) use ($reminderDays, $overdueWindow, &$sent) {
                foreach ($subscriptions as $subscription) {
                    if (! $subscription->siteOwner) {
                        continue;
                    }

                    if ($subscription->last_reminder_at && $subscription->last_reminder_at->isToday()) {
                        continue;
                    }

                    $daysLeft = (int) now()->startOfDay()->diffInDays($subscription->ends_at->startOfDay(), false);

                    if ($daysLeft < 0) {
                        if (abs($daysLeft) > $overdueWindow) {
                            continue;
                        }
                        $stage = 'overdue';
                    } elseif (in_array($daysLeft, array_map('intval', $reminderDays), true)) {
                        $stage = 'expiring';
                    } else {
                        continue;
                    }

                    Mail::to($subscription->siteOwner->email)
                        ->send(new BillingReminderMail($subscription->siteOwner, $subscription, $stage, $daysLeft));

                    $subscription->increment('reminder_count');
                    $subscription->update(['last_reminder_at' => now()]);
                    $sent++;

                    $this->line("→ {$subscription->siteOwner->email} ({$stage}, {$daysLeft}g)");
                }
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
