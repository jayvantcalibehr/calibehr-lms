<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushNotificationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class NotificationController extends Controller
{
    private function out($data, int $code, string $msg)
    {
        return response()->json(['data' => $data, 'code' => $code, 'message' => $msg]);
    }

    // =========================================================================
    // Register Device Token
    // =========================================================================

    /** POST /api/notifications/register-token */
    public function registerToken(Request $request)
    {
        $request->validate([
            'token'       => 'required|string',
            'device_type' => 'required|string', // 1: Android, 2: iOS
        ]);

        $uid = $request->user()->id;

        // Update or create token
        PushNotificationToken::updateOrCreate(
            ['emp_id' => $uid],
            [
                'token'       => $request->token,
                'device_type' => $request->device_type,
                'added_on'    => now(),
                'updated_on'  => now(),
            ]
        );

        return $this->out(null, 1, 'Token registered successfully.');
    }

    // =========================================================================
    // Send Notification to Single User
    // =========================================================================

    /** POST /api/notifications/send */
    public function sendNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'title'   => 'required|string',
            'body'    => 'required|string',
            'data'    => 'array',
        ]);

        $tokens = PushNotificationToken::where('emp_id', $request->user_id)->get();

        if ($tokens->isEmpty()) {
            return $this->out(null, 0, 'No device token found for this user.');
        }

        $sent = 0;
        foreach ($tokens as $tokenRecord) {
            $result = $this->sendFCM(
                $tokenRecord->token,
                $request->title,
                $request->body,
                $request->data ?? []
            );
            if ($result) $sent++;
        }

        return $this->out(['sent' => $sent], 1, "Notification sent to $sent device(s).");
    }

    // =========================================================================
    // Send Notification to All Users (New Course)
    // =========================================================================

    /** POST /api/notifications/send-all */
    public function sendToAll(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'body'  => 'required|string',
            'data'  => 'array',
        ]);

        $tokens = PushNotificationToken::pluck('token')->toArray();

        if (empty($tokens)) {
            return $this->out(null, 0, 'No device tokens found.');
        }

        // Send in chunks of 1000 (FCM limit)
        $chunks = array_chunk($tokens, 1000);
        $sent   = 0;

        foreach ($chunks as $chunk) {
            $result = $this->sendFCMMultiple(
                $chunk,
                $request->title,
                $request->body,
                $request->data ?? []
            );
            if ($result) $sent += count($chunk);
        }

        return $this->out(['sent' => $sent], 1, "Notification sent to $sent device(s).");
    }

    // =========================================================================
    // Send New Course Notification
    // =========================================================================

    /** POST /api/notifications/new-course */
    public function newCourseNotification(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);

        $course = \App\Models\Course::findOrFail($request->courseID);

        $title = 'New Course is live on LMS 🎓';
        $body  = $course->name . ' is now live on LMS. Click here to enroll yourself!';

        $tokens = PushNotificationToken::pluck('token')->toArray();

        if (empty($tokens)) {
            return $this->out(null, 0, 'No device tokens found.');
        }

        $chunks = array_chunk($tokens, 1000);
        $sent   = 0;

        foreach ($chunks as $chunk) {
            $result = $this->sendFCMMultiple($chunk, $title, $body, [
                'courseID' => (string)$course->id,
                'type'     => 'new_course',
            ]);
            if ($result) $sent += count($chunk);
        }

        return $this->out(['sent' => $sent], 1, "New course notification sent to $sent device(s).");
    }

    // =========================================================================
    // Send Course Assigned Notification
    // =========================================================================

    /** POST /api/notifications/course-assigned */
    public function courseAssignedNotification(Request $request)
    {
        $request->validate([
            'courseID' => 'required|integer',
            'userIDs'  => 'required|array',
        ]);

        $course = \App\Models\Course::findOrFail($request->courseID);

        $title = 'New Course has been assigned to you on LMS 📚';
        $body  = $course->name . ' has been assigned to you. Start learning now!';

        $sent = 0;
        foreach ($request->userIDs as $userId) {
            $tokens = PushNotificationToken::where('emp_id', $userId)->pluck('token')->toArray();
            foreach ($tokens as $token) {
                $result = $this->sendFCM($token, $title, $body, [
                    'courseID' => (string)$course->id,
                    'type'     => 'course_assigned',
                ]);
                if ($result) $sent++;
            }
        }

        return $this->out(['sent' => $sent], 1, "Course assigned notification sent to $sent device(s).");
    }

    // =========================================================================
    // FCM Helper — Single Token
    // =========================================================================

    private function sendFCM(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . env('FCM_SERVER_KEY'),
                'Content-Type'  => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to'           => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                    'sound' => 'default',
                    'badge' => '1',
                ],
                'data'         => $data,
                'priority'     => 'high',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    // =========================================================================
    // FCM Helper — Multiple Tokens
    // =========================================================================

    private function sendFCMMultiple(array $tokens, string $title, string $body, array $data = []): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . env('FCM_SERVER_KEY'),
                'Content-Type'  => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $tokens,
                'notification'     => [
                    'title' => $title,
                    'body'  => $body,
                    'sound' => 'default',
                    'badge' => '1',
                ],
                'data'             => $data,
                'priority'         => 'high',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
