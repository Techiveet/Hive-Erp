import type { NextConfig } from "next";

const skipBuildTypecheck =
  process.env.NEXT_SKIP_BUILD_TYPECHECK === "1" ||
  process.env.NEXT_SKIP_BUILD_TYPECHECK === "true";

const nextConfig: NextConfig = {
  output: "standalone",
  typescript: {
    ignoreBuildErrors: skipBuildTypecheck,
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
