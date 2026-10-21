<?php

namespace App\Http\Controllers;

use App\Models\QueueTicket;
use App\Services\QueueEmailNotificationService;

class EmailVerificationController extends Controller
{
    /**
     * The owner of the email clicks the link. Only the hash is stored, so the token is compared, never read back.
     */
    public function verify(QueueTicket $ticket, string $token, QueueEmailNotificationService $emails)
    {
        $back = redirect()->route('queue.show', ['ticket' => $ticket->public_token]);

        if ($ticket->isEmailVerified()) {
            return $back->with('status', 'Email ini sudah terverifikasi.');
        }

        abort_unless(
            $ticket->email_verification_hash !== null && hash_equals($ticket->email_verification_hash, hash('sha256', $token)),
            404
        );

        $ticket->forceFill(['email_verified_at' => now(), 'email_verification_hash' => null])->save();
        $emails->verified($ticket);

        return $back->with('status', 'Email Anda terverifikasi. Pemberitahuan antrean akan dikirim ke email ini.');
    }

    public function resend(QueueTicket $ticket, QueueEmailNotificationService $emails)
    {
        $sent = $emails->resendVerification($ticket);

        return redirect()
            ->route('queue.show', ['ticket' => $ticket->public_token])
            ->with('status', $sent
                ? 'Tautan verifikasi baru sudah kami kirim ke email Anda.'
                : 'Email ini sudah terverifikasi atau antrean sudah berakhir.');
    }
}
