import api from "@/modules/shared/api/http";
import type {
  NightClubOverview,
  NightClubReservation,
  NightClubServiceOrder,
  NightClubTable,
} from "@/modules/nightclub/types";

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

export const fetchNightClubOverview = async () =>
  (await api.get<NightClubOverview>("/nightclub/overview")).data;

export const fetchNightClubTables = async (params: Record<string, unknown> = {}) =>
  unwrapList<NightClubTable>((await api.get("/nightclub/tables", { params })).data);

export const createNightClubTable = async (payload: Record<string, unknown>) =>
  (await api.post<NightClubTable>("/nightclub/tables", payload)).data;

export const updateNightClubTable = async (id: number, payload: Record<string, unknown>) =>
  (await api.put<NightClubTable>(`/nightclub/tables/${id}`, payload)).data;

export const deleteNightClubTable = async (id: number) =>
  (await api.delete(`/nightclub/tables/${id}`)).data;

export const fetchNightClubReservations = async (params: Record<string, unknown> = {}) =>
  unwrapList<NightClubReservation>((await api.get("/nightclub/reservations", { params })).data);

export const createNightClubReservation = async (payload: Record<string, unknown>) =>
  (await api.post<NightClubReservation>("/nightclub/reservations", payload)).data;

export const updateNightClubReservation = async (id: number, payload: Record<string, unknown>) =>
  (await api.put<NightClubReservation>(`/nightclub/reservations/${id}`, payload)).data;

export const fetchNightClubServiceOrders = async (params: Record<string, unknown> = {}) =>
  unwrapList<NightClubServiceOrder>((await api.get("/nightclub/service-orders", { params })).data);

export const createNightClubServiceOrder = async (payload: Record<string, unknown>) =>
  (await api.post<NightClubServiceOrder>("/nightclub/service-orders", payload)).data;

export const updateNightClubServiceOrder = async (id: number, payload: Record<string, unknown>) =>
  (await api.put<NightClubServiceOrder>(`/nightclub/service-orders/${id}`, payload)).data;

export const closeNightClubServiceOrder = async (id: number) =>
  (await api.post<NightClubServiceOrder>(`/nightclub/service-orders/${id}/close`)).data;
