<?php

namespace App\Http\Controllers\Admin;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Http\Request;
use App\Services\WebhookDispatcher;
use Illuminate\Routing\Controller;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', [
            'title' => 'Planlar',
            'plans' => Plan::query()->withCount(['features'])->withCount('subscriptions')->orderBy('sort_order')->get(),
        ]);
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', [
            'title' => 'Plan: '.$plan->name,
            'plan' => $plan,
            'groups' => Feature::query()->orderBy('sort_order')->get()->groupBy('group'),
            'values' => $plan->features->pluck('pivot.value', 'id')->all(),
        ]);
    }

    public function update(Request $request, Plan $plan, WebhookDispatcher $webhooks)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'is_active' => ['nullable', 'boolean'],
            'features' => ['nullable', 'array'],
        ]);

        $plan->update([
            'name' => $data['name'],
            'price' => $data['price'],
            'billing_cycle' => $data['billing_cycle'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $sync = [];
        foreach ((array) $request->input('features', []) as $featureId => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $sync[$featureId] = ['value' => (string) $value];
        }
        $plan->features()->sync($sync);

        $webhooks->dispatchPlan($plan->id, 'plan.updated');

        return back()->with('status', __('Plan yeniləndi.'));
    }
}
