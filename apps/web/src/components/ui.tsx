import { useEffect, useId, useRef, cloneElement, isValidElement, type ReactElement, type ReactNode } from 'react';
import { ArrowUpRight, X, LoaderCircle, Inbox, AlertCircle, Check } from 'lucide-react';
export function Button({ children, onClick, variant = 'primary', disabled = false, type = 'button', className = '', title }: { children: ReactNode; onClick?: () => void; variant?: 'primary' | 'secondary' | 'ghost' | 'danger'; disabled?: boolean; type?: 'button' | 'submit'; className?: string; title?: string }) {
 return <button type={type} onClick={onClick} className={'button ' + variant + ' ' + className} disabled={disabled} title={title}>{children}</button>;
}
export function Badge({ value }: { value: string }) { return <span className={'badge ' + value.replace(/[^a-z_]/g, '')}><span />{value.replace(/_/g, ' ')}</span>; }
export function PageHeading({ eyebrow, title, description, action }: { eyebrow: string; title: string; description: string; action?: ReactNode }) {
 return <div className="page-heading"><div><div className="eyebrow">{eyebrow}</div><h1>{title}</h1><p>{description}</p></div>{action && <div className="heading-actions">{action}</div>}</div>;
}
export function Empty({ title = 'Nothing here yet', text = 'Your records will appear here as you use the platform.', action }: { title?: string; text?: string; action?: ReactNode }) { return <div className="empty"><div className="empty-icon"><Inbox size={22}/></div><h3>{title}</h3><p>{text}</p>{action}</div>; }
export function Loading() { return <div className="loading" role="status"><LoaderCircle className="spin" size={18}/> Loading workspace data…</div>; }
export function ErrorBox({ message }: { message: string }) { return <div role="alert" className="error-box"><AlertCircle size={17}/>{message}</div>; }
export function Modal({ title, children, close, wide = false }: { title: string; children: ReactNode; close: () => void; wide?: boolean }) {
 const ref = useRef<HTMLDialogElement>(null), titleId = useId();
 useEffect(() => { const dialog = ref.current; dialog?.showModal(); return () => { dialog?.close(); }; }, []);
 return <dialog ref={ref} className={'modal ' + (wide ? 'wide' : '')} aria-labelledby={titleId} onCancel={e => { e.preventDefault(); close(); }} onClick={e => { if (e.target === ref.current) close(); }}><div className="modal-head"><h2 id={titleId}>{title}</h2><button className="icon-button" aria-label="Close dialog" onClick={close}><X size={19}/></button></div><div className="modal-body">{children}</div></dialog>;
}
export function Field({ label, children, hint }: { label: string; children: ReactNode; hint?: string }) { const id=useId(); return <div className="field"><label htmlFor={id}>{label}</label>{isValidElement(children)?cloneElement(children as ReactElement<{id?:string;'aria-describedby'?:string}>,{id,'aria-describedby':hint?id+'-hint':undefined}):children}{hint && <small id={id+'-hint'}>{hint}</small>}</div>; }
export function JsonBlock({ value }: { value: unknown }) { return <pre className="json-block">{JSON.stringify(value, null, 2)}</pre>; }
export function TabBar({ tabs, value, change }: { tabs: { id: string; label: string }[]; value: string; change: (id: string) => void }) { return <div className="tabs" role="tablist">{tabs.map(tab => <button key={tab.id} role="tab" aria-selected={tab.id === value} className={tab.id === value ? 'active' : ''} onClick={() => change(tab.id)}>{tab.label}</button>)}</div>; }
export function PanelTitle({ title, meta, action }: { title: string; meta?: string; action?: ReactNode }) { return <div className="panel-title"><div><h2>{title}</h2>{meta && <p>{meta}</p>}</div>{action}</div>; }
export const ArrowLink = ({ children, page }: { children: ReactNode; page: string }) => <a className="text-link" href={'#' + page}>{children}<ArrowUpRight size={14}/></a>;
export function Time({ value }: { value: string | null | undefined }) { if (!value) return <>—</>; return <time dateTime={value} title={new Date(value).toLocaleString()}>{new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}</time>; }
export function CopyButton({ value, label = 'Copy' }: { value: string; label?: string }) { return <Button variant="secondary" onClick={() => { void navigator.clipboard.writeText(value); }}>{label}</Button>; }
export const Success = ({ children }: { children: ReactNode }) => <div className="success-box"><Check size={17}/>{children}</div>;
