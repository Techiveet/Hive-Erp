// import type { NextConfig } from "next";

// const nextConfig: NextConfig = {
//   // Try it here first (Root level)
//   allowedDevOrigins: ["http://127.0.0.1:3000", "http://localhost:3000"],
  
//   // If the terminal still says "Unrecognized key", use this instead:
//   /*
//   experimental: {
//     allowedDevOrigins: ["http://127.0.0.1:3000", "http://localhost:3000"],
//   },
//   */
  
//   // Ensure your output is correct for Docker/Standalone if needed
//   output: 'standalone', 
// };

// export default nextConfig;

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: "standalone",
};
export default nextConfig;