<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;

class OrgSubscription
{
    public const TRIAL_DAYS = 7;
    public const PLAN_BASIC = 'basic';
    public const PLAN_PRO = 'pro';

    public static function registrationAt(User $org): ?CarbonImmutable
    {
        $value = $org->registered_at ?? $org->created_at;

        return $value ? CarbonImmutable::parse($value) : null;
    }

    public static function trialEndsAt(User $org): ?CarbonImmutable
    {
        $registeredAt = self::registrationAt($org);

        return $registeredAt?->addDays(self::TRIAL_DAYS);
    }

    public static function normalizePlan(?string $plan): ?string
    {
        $value = strtolower(trim((string) $plan));

        return in_array($value, [self::PLAN_BASIC, self::PLAN_PRO], true) ? $value : null;
    }

    public static function hasActivePaidPlan(User $org): bool
    {
        $plan = self::normalizePlan($org->subscription_plan);
        if ($plan === null || empty($org->subscription_ends_at)) {
            return false;
        }

        return CarbonImmutable::parse($org->subscription_ends_at)->isFuture();
    }

    public static function isTrialActive(User $org): bool
    {
        $trialEndsAt = self::trialEndsAt($org);

        return $trialEndsAt?->isFuture() ?? false;
    }

    public static function canCreateVisits(User $org): bool
    {
        return self::isTrialActive($org) || self::hasActivePaidPlan($org);
    }

    public static function canManageStaff(User $org): bool
    {
        if (self::isTrialActive($org)) {
            return true;
        }

        return self::hasActivePaidPlan($org) && self::normalizePlan($org->subscription_plan) === self::PLAN_PRO;
    }

    public static function needsPayment(User $org): bool
    {
        return ! self::canCreateVisits($org);
    }

    public static function status(User $org): array
    {
        $trialEndsAt = self::trialEndsAt($org);
        $subscriptionEndsAt = $org->subscription_ends_at ? CarbonImmutable::parse($org->subscription_ends_at) : null;
        $normalizedPlan = self::normalizePlan($org->subscription_plan);
        $paidActive = self::hasActivePaidPlan($org);
        $trialActive = self::isTrialActive($org);

        return [
            'registered_at' => self::registrationAt($org)?->toIso8601String(),
            'trial_ends_at' => $trialEndsAt?->toIso8601String(),
            'subscription_plan' => $normalizedPlan,
            'subscription_ends_at' => $subscriptionEndsAt?->toIso8601String(),
            'subscription_active' => $paidActive,
            'is_trial_active' => $trialActive,
            'can_create_visits' => self::canCreateVisits($org),
            'can_manage_staff' => self::canManageStaff($org),
            'needs_subscription_payment' => self::needsPayment($org),
        ];
    }
}
