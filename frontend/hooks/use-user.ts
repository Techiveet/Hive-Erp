import { useState, useEffect, useCallback } from 'react';

export function useUser() {
  const [user, setUser] = useState<any>(null);
  const [isLoaded, setIsLoaded] = useState(false);

  const loadFromStorage = useCallback(() => {
    const userStr = localStorage.getItem("hive_user");
    if (!userStr) {
      setUser(null);
      setIsLoaded(true);
      return;
    }

    try {
      const parsed = JSON.parse(userStr);
      setUser(parsed);
    } catch (e) {
      console.error("Failed to parse hive_user context", e);
      setUser(null);
    }
    setIsLoaded(true);
  }, []);

  useEffect(() => {
    loadFromStorage();
    window.addEventListener("hive_security_cleared", loadFromStorage);
    return () => window.removeEventListener("hive_security_cleared", loadFromStorage);
  }, [loadFromStorage]);

  return { user, isLoaded };
}
