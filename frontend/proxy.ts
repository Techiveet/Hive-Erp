import type { NextRequest } from 'next/server';
import { NextResponse } from 'next/server';

export function proxy(request: NextRequest) {
  // 1. Check for the marker cookie
  const token = request.cookies.get('hive_token')?.value;
  const { pathname } = request.nextUrl;

  // 2. If on Dashboard but NO token -> Redirect to Login
  if (pathname.startsWith('/dashboard')) {
    if (!token) {
      const signInUrl = new URL('/auth/sign-in', request.url);
      signInUrl.searchParams.set('callbackUrl', pathname);
      return NextResponse.redirect(signInUrl);
    }
  }

  // 3. If on Login but HAVE token -> Redirect to Dashboard
  if (pathname.startsWith('/auth/sign-in')) {
    if (token) {
      return NextResponse.redirect(new URL('/dashboard', request.url));
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/dashboard/:path*', '/auth/:path*'],
};