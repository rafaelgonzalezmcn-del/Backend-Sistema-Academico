<?php

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

if (!function_exists('log_activity')) {
    function log_activity($description, $subject = null, $changes = null)
    {
        try {
            $request = request();
            $user = $request->user();
            
            ActivityLog::create([
                'description' => $description,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject ? $subject->id : null,
                'user_id' => $user ? $user->id : null,
                'changes' => $changes,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Silently fail - no debería romper la aplicación principal
            Log::error('Error logging activity: ' . $e->getMessage());
        }
    }
}
