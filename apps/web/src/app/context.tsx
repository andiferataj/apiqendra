import { createContext, useContext, useEffect, useState } from 'react';
import { api } from '../lib/api';
import type { User, Workspace } from '../lib/types';
type Context = { user: User; workspace: Workspace; demo: boolean; epoch: number; refresh: () => void; notify: (message: string, error?: boolean) => void };
export const AppContext = createContext<Context | null>(null);
export function useApp() { const ctx = useContext(AppContext); if (!ctx) throw new Error('Missing application context'); return ctx; }
export function useQuery<T>(path: string, poll = 0) {
 const { epoch, workspace } = useApp();
 const [state, setState] = useState<{ data: T | null; loading: boolean; error: string }>({ data: null, loading: true, error: '' });
 useEffect(() => {
  let active = true;
  setState({ data: null, loading: true, error: '' });
  const load = async () => { try { const data = await api<T>(path); if (active) setState({ data, loading: false, error: '' }); } catch (e) { if (active) setState(s => ({ ...s, loading: false, error: (e as Error).message })); } };
  void load();
  const timer = poll ? setInterval(() => { void load(); }, poll) : undefined;
  return () => { active = false; if (timer) clearInterval(timer); };
 }, [path, epoch, workspace.id, poll]);
 return state;
}
