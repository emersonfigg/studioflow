<?php

namespace App\Services;

use App\Mail\MembershipIdentityCode;
use App\Models\MembershipPublicIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipPublicIdentityService
{
    public function issue(Request $request, int $companyId, string $email): void
    {
        $email = mb_strtolower(trim($email));
        $identity = MembershipPublicIdentity::firstOrCreate(['company_id' => $companyId,
            'email_hash' => hash_hmac('sha256', $email, config('app.key'))], ['email' => $email]);
        $nonce = Str::random(64);
        $reference = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);
        DB::table('membership_identity_challenges')->insert(['id' => $reference, 'identity_id' => $identity->id,
            'session_hash' => hash('sha256', $nonce), 'code_hash' => hash_hmac('sha256', $reference.$code, config('app.key')),
            'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);
        $request->session()->put('membership_challenge_'.$companyId, [$reference, $nonce]);
        Mail::to($email)->send(new MembershipIdentityCode($code));
    }

    public function verify(Request $request, int $companyId, string $code): void
    {
        [$id, $nonce] = $request->session()->get('membership_challenge_'.$companyId, [null, null]);
        $identityId = DB::transaction(function () use ($id, $nonce, $code, $companyId) {
            $challenge = DB::table('membership_identity_challenges')->where('id', $id)->lockForUpdate()->first();
            if (! $challenge || ! $nonce || $challenge->consumed_at || $challenge->attempts >= 5
                || now()->gte($challenge->expires_at) || ! hash_equals($challenge->session_hash, hash('sha256', $nonce))
                || ! MembershipPublicIdentity::whereKey($challenge->identity_id)->where('company_id', $companyId)->exists()) {
                return null;
            }
            DB::table('membership_identity_challenges')->where('id', $id)->increment('attempts');
            if (! hash_equals($challenge->code_hash, hash_hmac('sha256', $id.$code, config('app.key')))) {
                return null;
            }
            DB::table('membership_identity_challenges')->where('id', $id)->update(['consumed_at' => now()]);

            return $challenge->identity_id;
        }, 3);
        if (! $identityId) {
            throw ValidationException::withMessages(['code' => 'Código inválido ou expirado. Solicite outro código.']);
        }
        $request->session()->regenerate();
        $request->session()->forget('membership_challenge_'.$companyId);
        $request->session()->put('membership_identity_'.$companyId, ['id' => $identityId, 'until' => now()->addMinutes(30)->timestamp]);
    }

    public function current(Request $request, int $companyId): ?MembershipPublicIdentity
    {
        $proof = $request->session()->get('membership_identity_'.$companyId);

        return is_array($proof) && ($proof['until'] ?? 0) > now()->timestamp
            ? MembershipPublicIdentity::where('company_id', $companyId)->find($proof['id'] ?? 0) : null;
    }
}
