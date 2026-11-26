<?php

namespace App\Http\Controllers;

use App\Models\Reading;
use App\Models\Transformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
   public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mac_address'  => 'required|string|max:20',
            'msg_id'       => 'nullable|integer',
            'temp'         => 'required|numeric',
            'hum'          => 'required|numeric',
            'alarm_active' => 'required|boolean',
        ]);

        $mac = strtoupper(trim($data['mac_address']));

        $transformer = Transformer::firstOrCreate(
            ['mac_address' => $mac],
            ['is_assigned' => false]
        );

        if (isset($data['msg_id']) && $transformer->last_msg_id === (int) $data['msg_id']) {
            return response()->json(['status' => 'duplicate_ignored']);
        }

        $transformer->update([
            'last_temp'    => $data['temp'],
            'last_hum'     => $data['hum'],
            'alarm_active' => $data['alarm_active'],
            'last_msg_id'  => $data['msg_id'] ?? $transformer->last_msg_id,
            'last_seen_at' => now(),
        ]);

        Reading::create([
            'transformer_id' => $transformer->id,
            'msg_id'         => $data['msg_id'] ?? null,
            'temp'           => $data['temp'],
            'hum'            => $data['hum'],
            'alarm_active'   => $data['alarm_active'],
            'created_at'     => now(),
        ]);

        return response()->json([
            'status'      => 'ok',
            'is_assigned' => $transformer->is_assigned,
        ]);
    }
}