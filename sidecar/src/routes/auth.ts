/**
 * Auth utility routes for wallet-ownership proof flows.
 *
 * These routes are intended for server-to-server use by the main site and
 * marketplace PHP endpoints (not direct browser exposure).
 */
import type { Express, Request, Response, NextFunction } from 'express';
import { checkSignature, resolveRewardAddress } from '@meshsdk/core';
import { z } from 'zod';

const SignaturePayloadSchema = z.object({
    signature: z.string().min(16),
    key:       z.string().min(16),
});

const VerifySignatureSchema = z.object({
    signed_address: z.string().min(8).max(256),
    nonce:          z.string().min(8).max(1024),
    signature:      SignaturePayloadSchema,
});

const RewardAddressSchema = z.object({
    address: z.string().min(8).max(256),
});

function normalizeAddress(raw: string): string
{
    const t = raw.trim();
    return t.startsWith('0x') ? t.slice(2) : t;
}
function toHexUtf8(raw: string): string
{
    return Buffer.from(raw, 'utf8').toString('hex');
}

function nonceCandidates(rawNonce: string): string[]
{
    const raw = rawNonce.trim();
    if (!raw) return [];
    const candidates = new Set<string>([raw]);
    const utf8Hex = toHexUtf8(raw);
    if (utf8Hex) candidates.add(utf8Hex);
    return Array.from(candidates);
}

function toRewardAddress(address: string): string | null
{
    const v = normalizeAddress(address);
    if (v.startsWith('stake')) return v;
    try {
        const resolved = resolveRewardAddress(v);
        return typeof resolved === 'string' && resolved !== '' ? resolved : null;
    } catch {
        return null;
    }
}

export function mountAuthRoutes(app: Express): void
{
    /**
     * POST /auth/verify-signature
     *
     * Verifies CIP-30 signData output against the provided address + nonce.
     * Response includes the best-effort stake/reward address for ownership
     * comparisons.
     */
    app.post('/auth/verify-signature', (req: Request, res: Response, next: NextFunction) => {
        try {
            const parsed = VerifySignatureSchema.safeParse(req.body);
            if (!parsed.success) {
                return res.status(400).json({ ok: false, error: 'invalid_request', issues: parsed.error.issues });
            }

            const signedAddress = normalizeAddress(parsed.data.signed_address);
            const nonce         = parsed.data.nonce;
            const signaturePayload = {
                signature: parsed.data.signature.signature,
                key: parsed.data.signature.key,
            };

            let valid = false;
            for (const candidate of nonceCandidates(nonce)) {
                try {
                    if (checkSignature(candidate, signaturePayload, signedAddress)) {
                        valid = true;
                        break;
                    }
                } catch {
                    // keep trying candidates
                }
            }

            return res.json({
                ok: true,
                signature_valid: valid,
                reward_address: valid ? toRewardAddress(signedAddress) : null,
            });
        } catch (err) {
            next(err);
        }
    });

    /**
     * POST /auth/reward-address
     *
     * Converts a payment/base/stake address into its stake (reward) address.
     * Returns null when conversion is not possible.
     */
    app.post('/auth/reward-address', (req: Request, res: Response, next: NextFunction) => {
        try {
            const parsed = RewardAddressSchema.safeParse(req.body);
            if (!parsed.success) {
                return res.status(400).json({ ok: false, error: 'invalid_request', issues: parsed.error.issues });
            }
            return res.json({
                ok: true,
                reward_address: toRewardAddress(parsed.data.address),
            });
        } catch (err) {
            next(err);
        }
    });
}
