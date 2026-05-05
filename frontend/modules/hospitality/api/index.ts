import api from "@/modules/shared/api/http";
import type {
  HospitalityLocation,
  HospitalityReservation,
  HospitalityServiceOrder,
  HospitalityCustomer,
  HospitalityEvent,
  HospitalityMenuItem,
  HospitalityOverview,
} from "@/modules/hospitality/types";

type Paginated<T> = {
  data: T[];
};

const unwrapList = <T>(payload: unknown): T[] => {
  if (Array.isArray(payload)) {
    return payload as T[];
  }

  if (payload && typeof payload === "object" && Array.isArray((payload as Paginated<T>).data)) {
    return (payload as Paginated<T>).data;
  }

  return [];
};

export const fetchHospitalityOverview = async () =>
  (await api.get<HospitalityOverview>("/hospitality/overview")).data;

export const fetchHospitalityTables = async (params: Record<string, unknown> = {}) =>
  unwrapList<HospitalityLocation>((await api.get("/hospitality/tables", { params })).data);

export const createHospitalityTable = async (payload: Record<string, unknown>) =>
  (await api.post<HospitalityLocation>("/hospitality/tables", payload)).data;

export const updateHospitalityTable = async (id: number, payload: Record<string, unknown>) =>
  (await api.put<HospitalityLocation>(`/hospitality/tables/${id}`, payload)).data;

export const deleteHospitalityTable = async (id: number) =>
  (await api.delete(`/hospitality/tables/${id}`)).data;

export const fetchHospitalityReservations = async (params: Record<string, unknown> = {}) =>
  unwrapList<HospitalityReservation>((await api.get("/hospitality/reservations", { params })).data);

export const createHospitalityReservation = async (payload: Record<string, unknown>) =>
  (await api.post<HospitalityReservation>("/hospitality/reservations", payload)).data;

export const updateHospitalityReservation = async (id: number, payload: Record<string, unknown>) =>
  (await api.put<HospitalityReservation>(`/hospitality/reservations/${id}`, payload)).data;

export const fetchHospitalityServiceOrders = async (params: Record<string, unknown> = {}) =>
  unwrapList<HospitalityServiceOrder>((await api.get("/hospitality/service-orders", { params })).data);

export const createHospitalityServiceOrder = async (payload: Record<string, unknown>) =>
  (await api.post<HospitalityServiceOrder>("/hospitality/service-orders", payload)).data;

export const updateHospitalityServiceOrder = async (id: number, payload: Record<string, unknown>) =>
  (await api.put<HospitalityServiceOrder>(`/hospitality/service-orders/${id}`, payload)).data;

export const closeHospitalityServiceOrder = async (id: number) =>
  (await api.post<HospitalityServiceOrder>(`/hospitality/service-orders/${id}/close`)).data;

export const fetchHospitalityMenuItems = async (params: Record<string, unknown> = {}) =>
  unwrapList<HospitalityMenuItem>((await api.get("/hospitality/menu-items", { params })).data);

export const createHospitalityMenuItem = async (payload: Record<string, unknown>) =>
  (await api.post<HospitalityMenuItem>("/hospitality/menu-items", payload)).data;

export const fetchHospitalityEvents = async (params: Record<string, unknown> = {}) =>
  unwrapList<HospitalityEvent>((await api.get("/hospitality/events", { params })).data);

export const createHospitalityEvent = async (payload: Record<string, unknown>) =>
  (await api.post<HospitalityEvent>("/hospitality/events", payload)).data;

export const fetchHospitalityCustomers = async (params: Record<string, unknown> = {}) =>
  unwrapList<HospitalityCustomer>((await api.get("/hospitality/customers", { params })).data);

export const splitHospitalityBill = async (orderId: number, payload: Record<string, unknown>) =>
  (await api.post(`/hospitality/service-orders/${orderId}/split-bill`, payload)).data;

export const fetchHospitalityBills = async (orderId: number) =>
  (await api.get(`/hospitality/service-orders/${orderId}/bills`)).data;
export const fetchFloorPlan = async (params: Record<string, unknown> = {}) =>
  (await api.get("/hospitality/space/floor-plan", { params })).data;

export const updateLocationStatus = async (id: number, status: string) =>
  (await api.patch(`/hospitality/space/locations/${id}/status`, { status })).data;

export const fetchGuestList = async () =>
  (await api.get("/hospitality/door/guest-list")).data;

export const guestCheckIn = async (id: number, actual_arrived_count: number) =>
  (await api.post(`/hospitality/door/check-in/${id}`, { actual_arrived_count })).data;
