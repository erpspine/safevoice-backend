<?php

namespace App\Services;

use App\Models\{Company, SubscriptionPlan, Subscription, Branch, Payment};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Start or renew a subscription for a company.
     * 
     * Logic:
     * - If branches have unexpired billing, add new duration to remaining days
     * - If branches have expired billing, start from payment date (today)
     */
    public function startOrRenew(
        Company $company,
        SubscriptionPlan $plan,
        int $durationMonths,
        array $paymentData, // method, reference, amount, billing_period, etc.
        array $selectedBranchIds = [] // Allow custom branch selection
    ): Subscription {
        return DB::transaction(function () use ($company, $plan, $durationMonths, $paymentData, $selectedBranchIds) {

            $today = Carbon::today();
            $billingPeriod = $paymentData['billing_period'] ?? 'monthly';
            $active = $company->activeSubscription(); // helper relation/scope

            // Determine start date based on active subscription
            $startsOn = $active
                ? Carbon::parse($active->ends_on)->addDay()  // stack periods
                : $today;

            $endsOn = (clone $startsOn)->addMonthsNoOverflow($durationMonths)->subDay();
            $graceUntil = (clone $endsOn)->addDays($plan->grace_days);

            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id'    => $plan->id,
                'billing_period' => $billingPeriod,
                'starts_on'  => $startsOn,
                'ends_on'    => $endsOn,
                'grace_until' => $graceUntil,
                'status'     => 'active',
                'auto_renew' => $paymentData['auto_renew'] ?? false,
                'renewal_method' => $paymentData['payment_method'] ?? null,
                'renewal_token'  => $paymentData['renewal_token'] ?? null,
            ]);

            // Update company's plan information
            $planName = strtolower($plan->name);
            $planType = 'free'; // default

            if (str_contains($planName, 'enterprise')) {
                $planType = 'enterprise';
            } elseif (str_contains($planName, 'premium')) {
                $planType = 'premium';
            } elseif (str_contains($planName, 'basic')) {
                $planType = 'basic';
            }

            $company->update([
                'plan' => $planType,
                'plan_id' => $plan->id,
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
     * 
     * Logic for each branch:
     * - If branch billing hasn't expired (activated_until > today): add new duration to remaining days
     * - If branch billing has expired (activated_until <= today or null): start from today
     */
    public function activateSelectedBranches(
        Company $company,
        SubscriptionPlan $plan,
        Carbon $subscriptionEndsOn,
        Subscription $subscription,
        array $branchIds
    ): void {
        $today = Carbon::today();

        // Validate that selected branches belong to the company
        $branches = Branch::where('company_id', $company->id)
            ->whereIn('id', $branchIds)
            ->get();

        $validBranchIds = $branches->pluck('id')->toArray();

        // Check if selection exceeds plan limits
        $count = count($validBranchIds);
        if ($plan->max_branches && $count > $plan->max_branches) {
            throw new \InvalidArgumentException(
                "Selected branches ({$count}) exceed plan limit ({$plan->max_branches})"
            );
        }

        // Calculate activation end date for each branch individually
        $attach = [];
        foreach ($branches as $branch) {
            // Determine the start date for this branch's new billing period
            $branchStartsOn = $today;

            // If the branch has unexpired billing, stack the new period
            if ($branch->activated_until && Carbon::parse($branch->activated_until)->gt($today)) {
                // Add remaining days to the new subscription end date
                $remainingDays = Carbon::parse($branch->activated_until)->diffInDays($today);
                $newActivatedUntil = (clone $subscriptionEndsOn)->addDays($remainingDays);
            } else {
                // Branch billing expired or never activated, start fresh from subscription end
                $newActivatedUntil = $subscriptionEndsOn;
            }

            // Update branch activation
            $branch->update([
                'is_active' => true,
                'activated_until' => $newActivatedUntil,
            ]);

            // Track which branches are activated by THIS subscription
            $attach[$branch->id] = [
                'activated_from'  => $branchStartsOn->toDateString(),
                'activated_until' => $newActivatedUntil->toDateString(),
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
     * Now includes stacking logic: if a branch is not expired, add new duration to existing end date.
     */
    public function activateBranchesForPlan(
        Company $company,
        SubscriptionPlan $plan,
        Carbon $until,
        ?Subscription $subscription = null,
        int $durationMonths = 1
    ): void {
        $q = Branch::where('company_id', $company->id)->orderBy('id');
        $cap = $plan->max_branches; // null = unlimited
        $branches = $cap ? $q->limit($cap)->get() : $q->get();
        $ids = $branches->pluck('id');

        $today = Carbon::today();
        $attach = [];

        // Process each branch with stacking logic
        foreach ($branches as $branch) {
            $currentActivatedUntil = $branch->activated_until ? Carbon::parse($branch->activated_until) : null;

            // If branch has active billing that hasn't expired, add new period to existing end date
            if ($currentActivatedUntil && $currentActivatedUntil->greaterThan($today)) {
                $branchStartDate = $currentActivatedUntil->copy()->addDay(); // Start from day after current end
                $branchEndDate = $branchStartDate->copy()->addMonthsNoOverflow($durationMonths)->subDay();
            } else {
                // Branch billing expired or never activated - start from today
                $branchStartDate = $today->copy();
                $branchEndDate = $branchStartDate->copy()->addMonthsNoOverflow($durationMonths)->subDay();
            }

            // Update this specific branch
            Branch::where('id', $branch->id)->update([
                'is_active' => true,
                'activated_until' => $branchEndDate,
            ]);

            // Prepare pivot data for tracking
            $attach[$branch->id] = [
                'activated_from'  => $branchStartDate->toDateString(),
                'activated_until' => $branchEndDate->toDateString(),
            ];
        }

        // Track which branches are activated by THIS subscription
        if ($subscription && !empty($attach)) {
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
