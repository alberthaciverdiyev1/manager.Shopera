<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\SiteOwner;
use App\Models\Subscription;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $usable = [SubscriptionStatus::ACTIVE->value, SubscriptionStatus::TRIALING->value, SubscriptionStatus::PAST_DUE->value];

        return view('admin.pages.dashboard', [
            'title' => 'İcmal',
            'stats' => [
                ['label' => 'Sahiblər', 'value' => SiteOwner::query()->count(), 'route' => route('admin.owners.index')],
                ['label' => 'Aktiv abunələr', 'value' => Subscription::query()->whereIn('status', $usable)->count(), 'route' => route('admin.plans.index')],
                ['label' => 'Aylıq gəlir (MRR)', 'value' => number_format((float) Subscription::query()->where('status', 'active')->sum('price'), 2).' ₼', 'route' => route('admin.plans.index')],
                ['label' => 'Feature-lar', 'value' => Feature::query()->count(), 'route' => route('admin.features.index')],
            ],
            'recentOwners' => SiteOwner::query()->with(['domains', 'currentSubscription.plan'])->latest('id')->limit(8)->get(),
            'plans' => Plan::query()->withCount('features')->orderBy('sort_order')->get(),
        ]);
    }
}
