import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

/**
 * Extracts a clean hostname from a URL string or hostname string.
 */
const extractHost = (value?: string | null): string | null => {
    if (!value) return null;

    try {
        // If it's a full URL
        if (value.includes('://')) {
            return new URL(value).hostname.toLowerCase();
        }
        // If it's just a hostname
        return value
            .trim()
            .toLowerCase()
            .replace(/\/.*$/, '')
            .replace(/:\d+$/, '') || null;
    } catch {
        return null;
    }
};

export function proxy(req: NextRequest) {
    const url = req.nextUrl;
    
    // Get the hostname (e.g., 'techive.gulfingot.com' or 'localhost')
    const hostname = req.headers.get('host') || ''; 
    const currentHost = hostname.split(':')[0].toLowerCase(); 

    // Get root domain from env or default to gulfingot.com
    const rootDomain = process.env.ROOT_DOMAIN || 'gulfingot.com';

    // Define domains that should be treated as "Central" (Main Landing Page)
    const centralDomains = new Set(
        [
            'localhost',
            '127.0.0.1',
            rootDomain, // apex
            `hive.${rootDomain}`, // main subdomain
            `hive-backend.${rootDomain}`,
            `hive-queue.${rootDomain}`,
            extractHost(process.env.NEXT_PUBLIC_APP_URL),
            ...((process.env.NEXT_PUBLIC_CENTRAL_DOMAINS ?? '').split(',').map((value) => extractHost(value))),
        ].filter((value): value is string => Boolean(value))
    );

    // 1. If it's a central domain, proceed as normal (serves main landing page at /)
    if (centralDomains.has(currentHost)) {
        return NextResponse.next();
    }

    // 2. Identify if this is a tenant subdomain
    const isTenantDomain = currentHost.endsWith(`.${rootDomain}`) || currentHost.endsWith('.localhost');

    if (isTenantDomain) {
        // Shared routes that should NOT be rewritten (Auth, Dashboard, etc.)
        // These components are built to be "host-aware".
        const sharedRoutes = ['/sign-in', '/auth', '/dashboard', '/api', '/_next', '/favicon.ico'];
        const isSharedRoute = sharedRoutes.some(path => url.pathname.startsWith(path));

        if (isSharedRoute) {
            return NextResponse.next();
        }

        // For the root path or other non-shared paths on a tenant domain,
        // we could rewrite to a [tenant] directory if it existed.
        // Since app/page.tsx handles the tenant landing view based on the hostname,
        // we simply pass through and let the component handle the logic.
        return NextResponse.next();
    }

    return NextResponse.next();
}

export const config = {
    matcher: ['/((?!api|_next/static|_next/image|favicon.ico|logos).*)'],
};
