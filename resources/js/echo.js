import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Inisialisasi Echo secara dinamis:
 * Mendukung Pusher (cocok untuk serverless/Vercel) dan Laravel Reverb.
 * Jika tidak ada key yang tersedia, mengembalikan null (fallback ke polling).
 */
export function createEcho(config = {}) {
    const bc = config.broadcast || {};
    const driver = (bc.driver || import.meta.env.VITE_BROADCAST_CONNECTION || (bc.pusher?.key || import.meta.env.VITE_PUSHER_APP_KEY ? 'pusher' : (bc.reverb?.key || import.meta.env.VITE_REVERB_APP_KEY ? 'reverb' : 'none'))).toLowerCase();

    // 1. Pusher (Rekomendasi utama untuk serverless Vercel)
    const pusherKey = bc.pusher?.key || import.meta.env.VITE_PUSHER_APP_KEY;
    if ((driver === 'pusher' || (!driver && pusherKey)) && pusherKey) {
        const cluster = bc.pusher?.cluster || import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';
        const scheme = bc.pusher?.scheme || import.meta.env.VITE_PUSHER_SCHEME || 'https';
        return new Echo({
            broadcaster: 'pusher',
            key: pusherKey,
            cluster: cluster,
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }

    // 2. Laravel Reverb (Bila di-host di server eksternal seperti VPS/Railway)
    const reverbKey = bc.reverb?.key || import.meta.env.VITE_REVERB_APP_KEY;
    const reverbHost = bc.reverb?.host || import.meta.env.VITE_REVERB_HOST;
    if ((driver === 'reverb' || (!driver && reverbKey)) && reverbKey && reverbHost) {
        const port = bc.reverb?.port ?? import.meta.env.VITE_REVERB_PORT ?? 443;
        const scheme = bc.reverb?.scheme || import.meta.env.VITE_REVERB_SCHEME || 'https';
        return new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: reverbHost,
            wsPort: port,
            wssPort: port,
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }

    return null;
}

export default createEcho;
