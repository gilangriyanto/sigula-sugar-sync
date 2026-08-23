import { keepPreviousData, useQuery } from "@tanstack/react-query";
import * as api from "@/lib/api/audit-log";

export function useAuditLog(params: api.AuditLogListParams = {}) {
  return useQuery({
    queryKey: ["audit-log", "list", params] as const,
    queryFn: () => api.getAuditLog(params),
    placeholderData: keepPreviousData,
  });
}
