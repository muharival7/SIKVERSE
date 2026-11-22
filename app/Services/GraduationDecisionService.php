<?php

namespace App\Services;

use App\Models\RiwayatKeputusanKelulusan;
use App\Models\User;

class GraduationDecisionService
{
    public function record(
        int $kelulusanSikId,
        ?string $statusSebelumnya,
        string $statusSesudahnya,
        User $actor,
        ?string $alasan
    ): RiwayatKeputusanKelulusan {
        return RiwayatKeputusanKelulusan::query()->create([
            'kelulusan_sik_id' => $kelulusanSikId,
            'actor_user_id' => $actor->id,
            'status_sebelumnya' => $statusSebelumnya,
            'status_sesudahnya' => $statusSesudahnya,
            'alasan' => $alasan,
        ]);
    }

    public function latestDecision(int $kelulusanSikId): ?RiwayatKeputusanKelulusan
    {
        return RiwayatKeputusanKelulusan::query()
            ->where('kelulusan_sik_id', $kelulusanSikId)
            ->latest()
            ->orderByDesc('id')
            ->first();
    }

    public function isLatestDecisionPassed(int $kelulusanSikId): bool
    {
        return $this->latestDecision($kelulusanSikId)?->status_sesudahnya === 'Lulus';
    }

    /**
     * Catatan tanpa keputusan baru diperlakukan sebagai data lama yang
     * dibekukan; keputusan baru selalu mengikat status terakhirnya.
     */
    public function explicitlyDecidedAs(int $kelulusanSikId, string $status): bool
    {
        $decision = $this->latestDecision($kelulusanSikId);

        return $decision !== null && $decision->status_sesudahnya === $status;
    }
}
