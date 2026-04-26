import { useState, useEffect, useCallback } from 'react';

export function useBusinessType() {
  const [businessType, setBusinessType] = useState<string | null>(null);
  const [isLoaded, setIsLoaded] = useState(false);

  const loadFromStorage = useCallback(() => {
    const userStr = localStorage.getItem("hive_user");
    if (!userStr) {
      setBusinessType(null);
      setIsLoaded(true);
      return;
    }

    try {
      const parsed = JSON.parse(userStr);
      // business_type is now a top-level field in the user payload (set by AuthController)
      const type: string | null = parsed.business_type ?? parsed.tenant?.business_type ?? null;
      setBusinessType(type);
    } catch (e) {
      console.error("Failed to parse hive_user context for business_type", e);
      setBusinessType(null);
    }
    setIsLoaded(true);
  }, []);

  useEffect(() => {
    loadFromStorage();
    window.addEventListener("hive_security_cleared", loadFromStorage);
    return () => window.removeEventListener("hive_security_cleared", loadFromStorage);
  }, [loadFromStorage]);

  const hasBusinessType = useCallback((type: string | string[]) => {
    if (!businessType) return true; // Fail open if no business type found or we fallback to allowing it
    if (Array.isArray(type)) {
      if (type.length === 0) return true;
      return type.includes(businessType);
    }
    return businessType === type;
  }, [businessType]);

  return { businessType, isLoaded, hasBusinessType };
}
