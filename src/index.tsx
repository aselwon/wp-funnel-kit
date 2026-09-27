import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Metric, totals } from './metrics';
import { apiUrl } from './api-url';
import './index.css';

type Funnel = {id:number; title:string; copy_a:string; copy_b:string; cta:string; ab_enabled:boolean; price_id:string; thank_you:string};
type Lead = {id:string; funnel_id:string; name:string; email:string; variant:string; status:string; created_at:string};
declare global { interface Window { funnelkit: { root:string; nonce:string } } }
const empty: Funnel = {id:0,title:'New funnel',copy_a:'Your offer starts here.',copy_b:'Try a different message.',cta:'Continue',ab_enabled:false,price_id:'',thank_you:''};
async function api<T>(path:string, method = 'GET', data?:unknown):Promise<T> {
    const response = await fetch(apiUrl(window.funnelkit.root, path), {method, credentials:'same-origin', headers:{'X-WP-Nonce':window.funnelkit.nonce,'Content-Type':'application/json'}, ...(data ? {body:JSON.stringify(data)} : {})});
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'Request failed. Please retry.');
    return result;
}
function App() {
    const [funnels,setFunnels] = useState<Funnel[]>([]);
    const [draft,setDraft] = useState<Funnel|null>(null);
    const [metrics,setMetrics] = useState<Metric[]>([]);
    const [leads,setLeads] = useState<Lead[]>([]);
    const [page,setPage] = useState(1);
    const [busy,setBusy] = useState(true);
    const [error,setError] = useState('');
    const [notice,setNotice] = useState('');
    async function refresh(nextPage = page) {
        const [fs,ms,ls] = await Promise.all([api<Funnel[]>('funnels'),api<Metric[]>('metrics'),api<Lead[]>('leads?page='+nextPage)]);
        setFunnels(fs);setMetrics(ms);setLeads(ls);setPage(nextPage);
        return fs;
    }
    useEffect(() => { refresh(1).then(fs => setDraft(fs[0] || {...empty})).catch(e=>setError(e.message)).finally(()=>setBusy(false)); }, []);
    async function perform(work:()=>Promise<void>) { setBusy(true);setError('');setNotice('');try {await work();} catch(e) {setError(e instanceof Error ? e.message : 'Request failed');} finally {setBusy(false);} }
    const counts = totals(metrics,draft?.id || 0);
    const edit = (key:keyof Funnel,value:string|boolean) => setDraft(draft ? {...draft,[key]:value} : null);
    return <main className="fk-admin">
        <header><div><span className="eyebrow">FUNNELKIT LITE</span><h1>Small funnel. Clear results.</h1><p>Edit your offer, capture interest and follow demo payments.</p></div><span className="badge">Mock payments enabled</span></header>
        {error && <div role="alert" className="error">{error}</div>}{notice && <div role="status" className="notice">{notice}</div>}
        <div className="toolbar"><label>Funnel <select disabled={busy} value={draft?.id || 0} onChange={e=>{setDraft(funnels.find(f=>f.id===Number(e.target.value)) || {...empty});setNotice('');}}><option value={0}>New funnel</option>{funnels.map(f=><option key={f.id} value={f.id}>{f.title}</option>)}</select></label><button disabled={busy} onClick={()=>setDraft({...empty})}>+ New funnel</button><button disabled={busy} onClick={()=>perform(async()=>{await refresh();setNotice('Data refreshed.');})}>Refresh data</button></div>
        <div className="stats"><article><strong>{counts.leads}</strong><span>Leads</span></article><article><strong>{counts.paid}</strong><span>Demo payments</span></article><article><strong>{counts.rate}%</strong><span>Lead → paid</span></article></div>
        {draft && <form onSubmit={e=>{e.preventDefault();perform(async()=>{const saved=await api<Funnel>(draft.id?'funnels/'+draft.id:'funnels',draft.id?'PUT':'POST',draft);await refresh();setDraft(saved);setNotice('Funnel saved.');});}}>
            <fieldset disabled={busy}><div className="editor"><section className="panel"><h2>Offer & copy</h2><label>Title<input required maxLength={200} value={draft.title} onChange={e=>edit('title',e.target.value)}/></label><label>Variant A<textarea value={draft.copy_a} onChange={e=>edit('copy_a',e.target.value)}/></label><label className="check"><input type="checkbox" checked={draft.ab_enabled} onChange={e=>edit('ab_enabled',e.target.checked)}/> Enable random A/B split</label><label>Variant B<textarea disabled={!draft.ab_enabled} value={draft.copy_b} onChange={e=>edit('copy_b',e.target.value)}/></label><label>Button label<input required value={draft.cta} onChange={e=>edit('cta',e.target.value)}/></label></section>
            <section className="panel"><h2>Checkout & publishing</h2><p>Mock mode records a simulated payment. No Stripe account or card is needed.</p><label>Stripe price ID (reserved; unused in mock)<input value={draft.price_id} onChange={e=>edit('price_id',e.target.value)} placeholder="price_…"/></label><label>Thank-you page URL (same site)<input type="url" value={draft.thank_you} onChange={e=>edit('thank_you',e.target.value)} placeholder="Optional: built-in confirmation by default"/></label><h3>Publish on a WordPress page</h3><p>Add a Shortcode block and paste:</p><code>{draft.id ? `[funnelkit id="${draft.id}"]` : 'Save this funnel to get its shortcode.'}</code><h3>A/B results</h3>{['A','B'].map(v=>{const row=metrics.find(m=>Number(m.funnel_id)===draft.id&&m.variant===v);return <p key={v}>Variant {v}: {row?.leads || 0} leads · {row?.paid || 0} paid</p>;})}<p className="muted">Conversion means payments ÷ captured leads. Page views are not tracked.</p></section></div>
            <div className="actions"><button className="primary" type="submit">{busy?'Saving…':'Save funnel'}</button>{draft.id>0&&<button type="button" onClick={()=>{if(window.confirm('Delete this funnel? Existing lead records will remain.'))perform(async()=>{await api('funnels/'+draft.id,'DELETE');const fs=await refresh();setDraft(fs[0]||{...empty});setNotice('Funnel deleted.');});}}>Delete funnel</button>}</div></fieldset>
        </form>}
        <section className="panel lead-panel"><h2>Recent leads · all funnels</h2><div className="table-scroll"><table><thead><tr>{['Name','Email','Funnel','Variant','Status','Created (UTC)'].map(h=><th key={h}>{h}</th>)}</tr></thead><tbody>{leads.map(l=><tr key={l.id}><td>{l.name}</td><td>{l.email}</td><td>#{l.funnel_id}</td><td>{l.variant}</td><td><span className={'status '+l.status}>{l.status}</span></td><td>{l.created_at}</td></tr>)}</tbody></table></div>{!leads.length&&<p>No leads on this page. Submit the public funnel form to start.</p>}<div className="actions"><button disabled={busy||page===1} onClick={()=>perform(async()=>{await refresh(page-1);})}>Previous</button><span>Page {page}</span><button disabled={busy||leads.length<50} onClick={()=>perform(async()=>{await refresh(page+1);})}>Next</button></div></section>
    </main>;
}
const root = document.getElementById('funnelkit-admin');
if(root) createRoot(root).render(<App/>);
