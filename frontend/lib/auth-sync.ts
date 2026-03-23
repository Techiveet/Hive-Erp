// lib/auth-sync.ts
import api from './api';

export const syncUserSession = async () => {
  try {
    // 🚀 Added cache-buster (?t=...) to force the browser to ask Laravel for fresh data
    const response = await api.get(`/user?t=${new Date().getTime()}`);
    const freshUserData = response.data;
    const localUserStr = localStorage.getItem("hive_user");
    
    if (localUserStr && freshUserData) {
      const localUser = JSON.parse(localUserStr);
      
      const updatedUser = {
        ...localUser,
        roles: freshUserData.roles || localUser.roles,
        permissions: freshUserData.permissions || localUser.permissions
      };

      // 🚀 Save the fresh data and ALWAYS dispatch the event
      localStorage.setItem("hive_user", JSON.stringify(updatedUser));
      window.dispatchEvent(new Event("hive_security_cleared"));
    }
  } catch (error) {
    console.error("Failed to sync security session with Hive Control", error);
  }
};