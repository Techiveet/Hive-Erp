const LAST_ACTIVITY_KEY = "hive_last_activity_at";
const LAST_SERVER_TOUCH_KEY = "hive_last_server_touch_at";

const canUseStorage = () => typeof window !== "undefined";

const readNumber = (key: string): number | null => {
  if (!canUseStorage()) return null;

  const rawValue = localStorage.getItem(key);
  if (!rawValue) return null;

  const parsed = Number(rawValue);
  return Number.isFinite(parsed) ? parsed : null;
};

export const getLastActivityAt = (): number | null => readNumber(LAST_ACTIVITY_KEY);

export const touchLastActivity = (timestamp = Date.now()): number => {
  if (canUseStorage()) {
    localStorage.setItem(LAST_ACTIVITY_KEY, String(timestamp));
  }

  return timestamp;
};

export const getLastServerTouchAt = (): number | null => readNumber(LAST_SERVER_TOUCH_KEY);

export const touchLastServerTouch = (timestamp = Date.now()): number => {
  if (canUseStorage()) {
    localStorage.setItem(LAST_SERVER_TOUCH_KEY, String(timestamp));
  }

  return timestamp;
};

export const initializeSessionActivity = (timestamp = Date.now()) => {
  touchLastActivity(timestamp);
  touchLastServerTouch(timestamp);
};

export const clearSessionActivity = () => {
  if (!canUseStorage()) return;

  localStorage.removeItem(LAST_ACTIVITY_KEY);
  localStorage.removeItem(LAST_SERVER_TOUCH_KEY);
};

