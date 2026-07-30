<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CampusNotificationService
{
    /**
     * Send WhatsApp notification via Gateway API (Fonnte / Generic Webhook).
     */
    public static function sendWhatsApp(string $targetPhone, string $message): array
    {
        $waToken = config('services.whatsapp.token', 'MOCK_WA_TOKEN_RAGI_CAMPUS');
        $endpoint = config('services.whatsapp.endpoint', 'https://api.fonnte.com/send');

        Log::info("SIMPEG WA NOTIFICATION -> Phone: {$targetPhone} | Message: {$message}");

        try {
            // Simulated or live API HTTP Post
            $response = Http::withHeaders([
                'Authorization' => $waToken,
            ])->post($endpoint, [
                'target' => $targetPhone,
                'message' => $message,
            ]);

            return [
                'status' => 'sent',
                'channel' => 'whatsapp',
                'target' => $targetPhone,
                'message' => $message,
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            // Fallback for demo/development environment
            return [
                'status' => 'simulated_sent',
                'channel' => 'whatsapp',
                'target' => $targetPhone,
                'message' => $message,
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Send Email Notification (Simulated/Log or Laravel Mail).
     */
    public static function sendEmail(string $targetEmail, string $subject, string $body): array
    {
        Log::info("SIMPEG EMAIL NOTIFICATION -> Email: {$targetEmail} | Subject: {$subject}");

        return [
            'status' => 'sent',
            'channel' => 'email',
            'target' => $targetEmail,
            'subject' => $subject,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
