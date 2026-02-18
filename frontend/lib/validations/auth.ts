// frontend/lib/validations/auth.ts

import { z } from "zod";

// 1. Login Schema (From your old implementation)
export const loginSchema = z.object({
  email: z.string().email("Please enter a valid email address"),
  password: z.string().min(1, "Password is required"),
});

export type LoginFormData = z.infer<typeof loginSchema>;

// 2. OTP Schema (Added for the new 2FA step)
export const otpSchema = z.object({
  code: z
    .string()
    .length(6, "OTP must be exactly 6 digits")
    .regex(/^\d+$/, "OTP must only contain numbers"),
});

export type OtpFormData = z.infer<typeof otpSchema>;