import type { NextConfig } from "next";

const skipBuildTypecheck =
  process.env.NEXT_SKIP_BUILD_TYPECHECK === "1" ||
  process.env.NEXT_SKIP_BUILD_TYPECHECK === "true";

const nextConfig: NextConfig = {
  output: "standalone",
  typescript: {
    ignoreBuildErrors: skipBuildTypecheck,
  },

  async rewrites() {
    const backendUrl = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8085";
    return [
      {
        source: "/api/v1/:path*",
        destination: `${backendUrl}/api/v1/:path*`,
      },
      {
        source: "/sanctum/:path*",
        destination: `${backendUrl}/sanctum/:path*`,
      },
    ];
  },

  webpack(config, { isServer }) {
    // Allow WASM imports (used by pdfjs-dist)
    config.experiments = {
      ...config.experiments,
      asyncWebAssembly: true,
    };
    return config;
  },
  turbopack: {},
};

export default nextConfig;
