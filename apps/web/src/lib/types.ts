export type User = { id: string; name: string; email: string };
export type Workspace = { id: string; name: string; environment: string; role: 'owner' | 'admin' | 'developer' | 'viewer' };
export type Resource = {
 id: string; name: string; workspace_id: string; created_at: string; updated_at: string;
 provider?: string; base_url?: string; credential_id?: string | null; status?: string; config?: { default_path?: string };
 version?: number; kind?: string; allowed_hosts?: string[]; expires_at?: string | null; event_types?: string[]; url?: string;
 endpoint_id?: string; destination_id?: string; mapping_id?: string | null; event_type?: string; spec?: Record<string, MappingRule>;
};
export type MappingRule = { path?: string; transform?: string; default?: unknown; literal?: unknown };
export type WebhookEvent = { id: string; source_id: string; event_type: string; endpoint_id: string; preview: Record<string, unknown>; status: string; received_at: string; simulated: boolean };
export type Delivery = { id: string; event_id: string; destination_id: string; status: string; attempt_count: number; run_attempts: number; generation: number; next_attempt_at: string | null; last_error: string | null; created_at: string; mapping_version: number | null };
export type Attempt = { id: string; number: number; generation: number; outcome: string; status_code: number | null; latency_ms: number; uncertain: boolean; diagnostic: string | null; created_at: string };
export type Overview = { events: number; delivery_counts: Record<string, number>; attempts: number; attempt_success_rate: number | null; p95_ms: number | null; backlog: number; series: { bucket: string; total: number; delivered: number }[]; recent_events: WebhookEvent[]; recent_deliveries: Delivery[]; range: string };
export type Paginated<T> = { data: T[]; current_page: number; last_page: number; total: number };
export type SimulationRun = { id: string; scenario: string; event_id: string | null; result: { error?: string; ingress_status?: number; validated?: boolean; duplicate?: boolean; destination_id?: string }; deliveries: Delivery[]; validated: boolean; created_at: string };
export type Log = { id: string; action?: string; resource_id?: string; actor_id?: string; outcome?: string; request_id?: string; method?: string; path?: string; status_code?: number; latency_ms?: number; metadata?: Record<string, unknown>; created_at: string };
export type AlertData = { rules: { id: string; name: string; kind: string; threshold: number; muted_until: string | null }[]; events: { id: string; message: string; resolved_at: string | null; created_at: string }[] };
