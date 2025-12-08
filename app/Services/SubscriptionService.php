<?php

namespace App\Services;

use App\Models\{Company, SubscriptionPlan, Subscription, Branch, Payment};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Start or renew a subscription for a company.
     */
    public function startOrRenew(
        Company $company,
        SubscriptionPlan $plan,
        int $durationMonths,
        array $paymentData, // method, reference, amount, etc.
        array $selectedBranchIds = [] // Allow custom branch selection
    ): Subscription {
        return DB::transaction(function () use ($company, $plan, $durationMonths, $paymentData, $selectedBranchIds) {

            $today = Carbon::today();
            $active = $company->activeSubscription(); // helper relation/scope

            $startsOn = $active
                ? Carbon::parse($active->ends_on)->addDay()  // stack periods
                : $today;

            $endsOn = (clone $startsOn)->addMonthsNoOverflow($durationMonths)->subDay();
            $graceUntil = (clone $endsOn)->addDays($plan->grace_days);

            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id'    => $plan->id,
                'starts_on'  => $startsOn,
                'ends_on'    => $endsOn,
                'grace_until' => $graceUntil,
                'status'     => 'active',
                'auto_renew' => $paymentData['auto_renew'] ?? false,
                'renewal_method' => $paymentData['payment_method'] ?? null,
                'renewal_token'  => $paymentData['renewal_token'] ?? null,
            ]);

            // Create payment record
            Payment::create([
                'company_id'           => $company->id,
                'subscription_plan_id' => $plan->id,
                'subscription_id'      => $subscription->id,
                'duration_months'      => $durationMonths,
                'amount_paid'          => $paymentData['amount_paid'],
                'payment_method'       => $paymentData['payment_method'],
                'payment_reference'    => $paymentData['payment_reference'] ?? null,
                'period_start'         => $startsOn,
                'period_end'           => $endsOn,
                'status'               => 'completed',
            ]);

            // Activate branches - either selected ones or auto-select based on plan
            if (!empty($selectedBranchIds)) {
                $this->activateSelectedBranches($company, $plan, $endsOn, $subscription, $selectedBranchIds);
            } else {
                $this->activateBranchesForPlan($company, $plan, $endsOn, $subscription);
            }

            return $subscription;
        });
    }

    /**
     * Activate specific branches selected by the user.
     */
    public function activateSelectedBranches(
        Company $company,
        SubscriptionPlan $plan,
        Carbon $until,
        Subscription $subscription,
        array $branchIds
    ): void {
        // Validate that selected branches belong to the company
        $validBranchIds = Branch::where('company_id', $company->id)
            ->whereIn('id', $branchIds)
            ->pluck('id')
            ->toArray();

        // Check if selection exceeds plan limits
        $count = count($validBranchIds);
        if ($plan->max_branches && $count > $plan->max_branches) {
            throw new \InvalidArgumentException(
                "Selected branches ({$count}) exceed plan limit ({$plan->max_branches})"
            );
        }

        // Activate selected branches
        Branch::whereIn('id', $validBranchIds)->update([
            'is_active' => true,
            'activated_until' => $until,
        ]);

        // Track which branches are activated by THIS subscription
        $attach = [];
        foreach ($validBranchIds as $branchId) {
            $attach[$branchId] = [
                'activated_from'  => now()->toDateString(),
                'activated_until' => $until->toDateString(),
            ];
        }
        $subscription->branches()->syncWithoutDetaching($attach);

        // Deactivate other branches not selected
        Branch::where('company_id', $company->id)
            ->whereNotIn('id', $validBranchIds)
            ->update(['is_active' => false, 'activated_until' => null]);
    }

    /**
     * Automatically activate branches based on plan limits (original logic).
     */
    public function activateBranchesForPlan(
        Company $company,
        SubscriptionPlan $plan,
        Carbon $until,
        ?Subscription $subscription = null
    ): void {
        $q = Branch::where('company_id', $company->id)->orderBy('id');
        $cap = $plan->max_branches; // null = unlimited
        $ids = $cap ? $q->limit($cap)->pluck('id') : $q->pluck('id');

        // Activate selected
        Branch::whereIn('id', $ids)->update([
            'is_active' => true,
            'activated_until' => $until,
        ]);

        // Track which branches are activated by THIS subscription
        if ($subscription) {
            $attach = [];
            foreach ($ids as $bid) {
                $attach[$bid] = [
                    'activated_from'  => now()->toDateString(),
                    'activated_until' => $until->toDateString(),
                ];
            }
            $subscription->branches()->syncWithoutDetaching($attach);
        }

        // Deactivate extras beyond cap
        if ($cap) {
            Branch::where('company_id', $company->id)
                ->whereNotIn('id', $ids)
                ->update(['is_active' => false, 'activated_until' => null]);
        }
    }

    /**
     * Get available branches for a company to select from.
     */
    public function getAvailableBranches(Company $company, SubscriptionPlan $plan): array
    {
        $branches = Branch::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'location', 'is_active', 'activated_until'])
            ->map(function ($branch) {
                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'location' => $branch->location,
                    'full_name' => "{$branch->name} - {$branch->location}",
                    'is_currently_active' => $branch->is_active,
                    'activated_until' => $branch->activated_until,
                ];
            });

        return [
            'branches' => $branches,
            'max_selectable' => $plan->max_branches,
            'plan_name' => $plan->name,
            'total_available' => $branches->count(),
        ];
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(Subscription $subscription, bool $immediatelyCancel = false): void
    {
        if ($immediatelyCancel) {
            $subscription->update(['status' => 'cancelled']);

            // Deactivate all branches immediately
            Branch::where('company_id', $subscription->company_id)
                ->update(['is_active' => false, 'activated_until' => null]);
        } else {
            // Cancel at period end
            $subscription->update(['cancel_at_period_end' => true]);
        }
    }

    /**
     * Extend subscription period.
     */
    public function extendSubscription(
        Subscription $subscription,
        int $additionalMonths,
        array $paymentData
    ): void {
        DB::transaction(function () use ($subscription, $additionalMonths, $paymentData) {
            $newEndDate = Carbon::parse($subscription->ends_on)->addMonthsNoOverflow($additionalMonths);
            $newGraceUntil = (clone $newEndDate)->addDays($subscription->plan->grace_days);

            $subscription->update([
                'ends_on' => $newEndDate,
                'grace_until' => $newGraceUntil,
            ]);

            // Create payment record for extension
            Payment::create([
                'company_id'           => $subscription->company_id,
                'subscription_plan_id' => $subscription->plan_id,
                'subscription_id'      => $subscription->id,
                'duration_months'      => $additionalMonths,
                'amount_paid'          => $paymentData['amount_paid'],
                'payment_method'       => $paymentData['payment_method'],
                'payment_reference'    => $paymentData['payment_reference'] ?? null,
                'period_start'         => Carbon::parse($subscription->ends_on)->addDay(),
                'period_end'           => $newEndDate,
                'status'               => 'completed',
            ]);

            // Update activated_until for all associated branches
            Branch::whereHas('subscriptions', function ($query) use ($subscription) {
                $query->where('subscription_id', $subscription->id);
            })->update(['activated_until' => $newEndDate]);
        });
    }
}
