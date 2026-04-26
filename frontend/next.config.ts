import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "standalone",

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
