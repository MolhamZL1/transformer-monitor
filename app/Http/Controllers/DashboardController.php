<?php

namespace App\Http\Controllers;

use App\Models\Reading;
use App\Models\Transformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard');
    }

    public function data(): JsonResponse
    {
        $transformers = Transformer::orderByDesc('alarm_active')
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(function (Transformer $t) {
                $isOnline = $t->last_seen_at && $t->last_seen_at->gt(now()->subSeconds(30));

                return [
                    'id'           => $t->id,
                    'mac_address'  => $t->mac_address,
                    'name'         => $t->name,
                    'neighborhood' => $t->neighborhood,
                    'latitude'     => $t->latitude,
                    'longitude'    => $t->longitude,
                    'is_assigned'  => $t->is_assigned,
                    'last_temp'    => $t->last_temp,
                    'last_hum'     => $t->last_hum,
                    'alarm_active' => $t->alarm_active,
                    'is_online'    => $isOnline,
                    'last_seen'    => $t->last_seen_at ? $t->last_seen_at->diffForHumans() : 'غير متصل',
                ];
            });

        $recentAlarms = Reading::with('transformer:id,name,mac_address,neighborhood')
            ->where('alarm_active', true)
            ->latest('created_at')
            ->take(20)
            ->get()
            ->map(fn (Reading $r) => [
                'transformer_name' => $r->transformer->name ?? $r->transformer->mac_address,
                'neighborhood'     => $r->transformer->neighborhood ?? 'غير محدد',
                'temp'             => $r->temp,
                'hum'              => $r->hum,
                'time'             => $r->created_at->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'assigned'      => $transformers->where('is_assigned', true)->values(),
            'unassigned'    => $transformers->where('is_assigned', false)->values(),
            'active_alarms' => $transformers->where('alarm_active', true)->values(),
            'alarm_logs'    => $recentAlarms,
            'stats'         => [
                'total'      => $transformers->where('is_assigned', true)->count(),
                'alarms'     => $transformers->where('alarm_active', true)->count(),
                'unassigned' => $transformers->where('is_assigned', false)->count(),
                'online'     => $transformers->where('is_online', true)->count(),
            ],
        ]);
    }

    public function assign(Request $request, Transformer $transformer): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'neighborhood' => 'required|string|max:100',
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
        ]);

        $transformer->update([
            'name'         => $validated['name'],
            'neighborhood' => $validated['neighborhood'],
            'latitude'     => $validated['latitude'],
            'longitude'    => $validated['longitude'],
            'is_assigned'  => true,
        ]);

        return response()->json(['status' => 'assigned', 'transformer' => $transformer]);
    }

    public function history(Transformer $transformer): JsonResponse
    {
        $readings = $transformer->readings()
            ->latest('created_at')
            ->take(30)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Reading $r) => [
                'time'  => $r->created_at->format('H:i:s'),
                'temp'  => $r->temp,
                'hum'   => $r->hum,
                'alarm' => $r->alarm_active,
            ]);

        return response()->json([
            'name'     => $transformer->name ?? $transformer->mac_address,
            'readings' => $readings,
        ]);
    }

    public function destroy(Transformer $transformer): JsonResponse
    {
        $transformer->delete();
        return response()->json(['status' => 'deleted']);
    }
}