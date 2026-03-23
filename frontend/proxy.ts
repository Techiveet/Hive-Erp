import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export function proxy(req: NextRequest) {
    const url = req.nextUrl;
    
    // Get the hostname (e.g., 'apple.localhost:3000' or 'localhost:3000')
    const hostname = req.headers.get('host') || ''; 
    const currentHost = hostname.split(':')[0]; 

    const centralDomains = ['localhost', '127.0.0.1'];

    // Multi-tenant routing logic
    if (!centralDomains.includes(currentHost)) {
        const tenantId = currentHost.replace('.localhost', '');
        
        // 🚀 FIXED: Moved '/dashboard' out of exact matches...
        const exactSharedRoutes = ['/', '/reset-password'];
        
        // 🚀 ...and into prefix matches! Now /dashboard, /dashboard/security, 
        // /dashboard/settings, etc., will all map correctly.
        const prefixSharedRoutes = ['/sign-in', '/dashboard']; 

        const isExactMatch = exactSharedRoutes.includes(url.pathname);
        const isPrefixMatch = prefixSharedRoutes.some(prefix => url.pathname.startsWith(prefix));

        // If the user is visiting a shared app file, DO NOT rewrite the URL.
        if (isExactMatch || isPrefixMatch) {
            return NextResponse.next();
        }

        // For all other strictly tenant-isolated routes, execute the rewrite!
        return NextResponse.rewrite(new URL(`/${tenantId}${url.pathname}`, req.url));
    }

    return NextResponse.next();
}

export const config = {
    matcher: ['/((?!api|_next/static|_next/image|favicon.ico|logos).*)'],
};