<?php

use Lalalili\SubscriptionCore\Models\SubscriptionPlan;

it('reads feature flags from the configured features array', function (): void {
    $plan = new SubscriptionPlan([
        'features' => [
            'features' => [
                'advanced_reports' => true,
            ],
        ],
    ]);

    expect($plan->hasFeature('advanced_reports'))->toBeTrue();
});
