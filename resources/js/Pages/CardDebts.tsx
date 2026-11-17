import React from 'react';
import {router, useForm, usePage} from '@inertiajs/react';
import {AlertTriangle, Check, CreditCard, FileSpreadsheet, Undo2, Upload, X} from 'lucide-react';
import {Shell, money, Empty} from '../Components/Shell';

const BASE = '/admin/importar-dividas';
const mes = (d?: string | null) => {
  if (!d) return '—';
  const s = new Date(Number(d.slice(0, 4)), Number(d.slice(5, 7)) - 1, 1).toLocaleDateString('pt-BR', {month: 'short', year: 'numeric'});
  return s.replace('.', '').replace(' de ', '/');
};
const dataHora = (d: string) => new Date(d.replace(' ', 'T')).toLocaleString('pt-BR', {dateStyle: 'short', timeStyle: 'short'});

const STATUS: Record<string, [string, string]> = {
  new: ['Nova', 'bg-emerald-50 text-emerald-700'],
  exists: ['Já importada', 'bg-slate-100 text-slate-500'],
  deleted: ['Excluída no app', 'bg-amber-50 text-amber-700'],
};

function Field({label, children, error}: {label: string; children: React.ReactNode; error?: string}) {
  return <label className="block text-sm"><span className="block mb-1 font-semibold text-slate-600">{label}</span>{children}{error && <span className="block text-red-600 text-xs mt-1">{error}</span>}</label>;
}

function Stat({label, value, hint, tone = 'bg-slate-50'}: {label: string; value: React.ReactNode; hint?: React.ReactNode; tone?: string}) {
  return <div className={`rounded-xl p-4 ${tone}`}><p className="text-xs text-slate-500">{label}</p><p className="font-bold text-xl mt-1">{value}</p>{hint && <p className="text-xs text-slate-500 mt-1">{hint}</p>}</div>;
}

function UploadBox() {
  const f = useForm<{file: File | null}>({file: null});
  const [drag, setDrag] = React.useState(false);
  const input = React.useRef<HTMLInputElement>(null);
  const send = (file?: File | null) => {
    if (!file) return;
    f.transform(() => ({file}));
    f.post(`${BASE}/previa`, {forceFormData: true, preserveScroll: true, onFinish: () => { if (input.current) input.current.value = ''; }});
  };
  return <div className="panel p-6 mb-6">
    <div onDragOver={e => { e.preventDefault(); setDrag(true); }} onDragLeave={() => setDrag(false)} onDrop={e => { e.preventDefault(); setDrag(false); send(e.dataTransfer.files?.[0]); }} onClick={() => input.current?.click()}
      className={`cursor-pointer rounded-2xl border-2 border-dashed p-8 text-center transition-colors ${drag ? 'border-brand-500 bg-brand-50' : 'border-slate-200 hover:border-brand-500 hover:bg-slate-50'}`}>
      <Upload className="mx-auto text-brand-700" size={32}/>
      <p className="mt-3 font-semibold text-slate-700">{f.processing ? 'Lendo planilha…' : 'Arraste a planilha de dívidas do cartão aqui ou clique para escolher'}</p>
      <p className="text-xs text-slate-500 mt-1">Arquivo .xlsx com a aba “Lançamentos” (Pessoa, Descrição, Valor Total, Parcelas e Mês Início). Nada é gravado antes da confirmação.</p>
      <input ref={input} type="file" accept=".xlsx" className="hidden" onChange={e => send(e.target.files?.[0])}/>
    </div>
    {f.errors.file && <p className="text-red-600 text-sm mt-3">{f.errors.file}</p>}
  </div>;
}

function Preview({p, cards, categories, defaults}: any) {
  const {errors} = usePage<any>().props;
  const f = useForm<any>({hash: p.hash, credit_card_id: defaults.credit_card_id || '', category_id: defaults.category_id || ''});
  const [filtro, setFiltro] = React.useState<'all' | 'new'>(p.counts.new && p.counts.exists ? 'new' : 'all');
  const people: string[] = p.by_person.map((x: any) => x.person);
  const rows = p.rows.filter((r: any) => filtro === 'all' || r.status === 'new');
  const save = (e: React.FormEvent) => {
    e.preventDefault();
    f.transform((d: any) => ({...d, credit_card_id: d.credit_card_id || null, category_id: d.category_id || null}));
    f.post(BASE, {preserveScroll: true});
  };

  return <div className="panel mb-6 overflow-hidden">
    <div className="p-5 border-b bg-brand-50/60 flex flex-wrap gap-3 items-center justify-between">
      <div className="flex items-center gap-3"><FileSpreadsheet className="text-brand-700"/><div><h2 className="font-bold">Prévia da importação</h2><p className="text-xs text-slate-500">{p.file_name} · aba “{p.sheet}”</p></div></div>
      <button className="btn-plain text-sm flex items-center gap-1" onClick={() => router.post(`${BASE}/previa/descartar`)}><X size={16}/> Descartar</button>
    </div>
    <div className="p-5">
      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <Stat tone="bg-emerald-50" label="Compras novas" value={p.counts.new} hint={<>somando <b>{money(p.new_cents)}</b></>}/>
        <Stat label="Já importadas" value={p.counts.exists + p.counts.deleted} hint="serão puladas"/>
        <Stat label="Total na planilha" value={money(p.by_person.reduce((s: number, x: any) => s + x.total_cents, 0))} hint={`${p.rows.length} compra(s)`}/>
        <Stat tone={p.invalid.length ? 'bg-amber-50' : 'bg-slate-50'} label="Linhas ignoradas" value={p.invalid.length} hint={p.invalid.length ? 'com problema, veja abaixo' : 'nenhum problema'}/>
      </div>

      {p.warnings.length > 0 && <div className="mt-5 space-y-2">{p.warnings.map((w: string) =>
        <div key={w} className="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl p-3 flex gap-2"><AlertTriangle size={16} className="shrink-0 mt-0.5"/>{w}</div>)}</div>}

      <div className="grid xl:grid-cols-2 gap-5 mt-5">
        <div>
          <h3 className="font-bold mb-2">Por pessoa</h3>
          <div className="overflow-x-auto border rounded-xl"><table className="w-full text-sm text-left">
            <thead className="bg-slate-50 text-slate-500"><tr>{['Pessoa', 'Compras', 'Total na planilha', 'Novo'].map(h => <th key={h} className="px-4 py-2.5 font-semibold">{h}</th>)}</tr></thead>
            <tbody className="divide-y">{p.by_person.map((x: any) => <tr key={x.person}><td className="px-4 py-2.5 font-semibold">{x.person}</td><td className="px-4 py-2.5">{x.rows}</td><td className="px-4 py-2.5">{money(x.total_cents)}</td><td className="px-4 py-2.5 text-emerald-700 font-semibold">{money(x.new_cents)}</td></tr>)}</tbody>
          </table></div>
        </div>
        <div>
          <h3 className="font-bold mb-2">Parcelas por mês <span className="text-xs font-normal text-slate-500">(confira com o “Resumo” da planilha)</span></h3>
          <div className="overflow-x-auto border rounded-xl max-h-80 overflow-y-auto"><table className="w-full text-sm text-left">
            <thead className="bg-slate-50 text-slate-500 sticky top-0"><tr><th className="px-4 py-2.5 font-semibold">Mês</th>{people.map(n => <th key={n} className="px-4 py-2.5 font-semibold">{n}</th>)}<th className="px-4 py-2.5 font-semibold">Total</th></tr></thead>
            <tbody className="divide-y">{p.by_month.map((m: any) => <tr key={m.month}><td className="px-4 py-2 capitalize">{mes(m.month)}</td>{people.map(n => <td key={n} className="px-4 py-2">{m.people[n] ? money(m.people[n]) : '—'}</td>)}<td className="px-4 py-2 font-semibold">{money(m.total_cents)}</td></tr>)}</tbody>
          </table></div>
        </div>
      </div>

      <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
        <h3 className="font-bold">Compras</h3>
        {p.counts.new > 0 && p.counts.new < p.rows.length && <div className="flex gap-1 text-sm bg-slate-100 rounded-xl p-1">
          {([['new', `Só as novas (${p.counts.new})`], ['all', `Todas (${p.rows.length})`]] as const).map(([k, l]) =>
            <button key={k} type="button" onClick={() => setFiltro(k)} className={`px-3 py-1.5 rounded-lg ${filtro === k ? 'bg-white shadow-sm font-semibold' : 'text-slate-500'}`}>{l}</button>)}
        </div>}
      </div>
      <div className="mt-2 overflow-x-auto border rounded-xl max-h-[28rem] overflow-y-auto"><table className="w-full text-sm text-left">
        <thead className="bg-slate-50 text-slate-500 sticky top-0"><tr>{['Linha', 'Pessoa', 'Descrição', 'Valor total', 'Parcelas', 'Meses', 'Situação'].map(h => <th key={h} className="px-3 py-2.5 font-semibold whitespace-nowrap">{h}</th>)}</tr></thead>
        <tbody className="divide-y">{rows.map((r: any) => {
          const [label, tone] = STATUS[r.status];
          return <tr key={r.id} className={r.status === 'new' ? '' : 'text-slate-400'}>
            <td className="px-3 py-2 text-xs">{r.line}</td>
            <td className="px-3 py-2 font-semibold">{r.person}</td>
            <td className="px-3 py-2">{r.description}</td>
            <td className="px-3 py-2 whitespace-nowrap">{money(r.amount_cents)}</td>
            <td className="px-3 py-2 whitespace-nowrap">{r.installments > 1 ? `${r.installments}x de ${money(r.monthly_cents)}` : 'À vista'}</td>
            <td className="px-3 py-2 whitespace-nowrap capitalize">{r.installments > 1 ? `${mes(r.start_month)} → ${mes(r.end_month)}` : mes(r.start_month)}</td>
            <td className="px-3 py-2"><span className={`text-xs rounded-full px-2.5 py-1 whitespace-nowrap ${tone}`}>{label}</span></td>
          </tr>;
        })}</tbody>
      </table></div>

      {p.invalid.length > 0 && <div className="mt-5">
        <h3 className="font-bold mb-2 text-amber-800">Linhas ignoradas</h3>
        <ul className="text-sm border border-amber-200 rounded-xl divide-y divide-amber-100 bg-amber-50/50">{p.invalid.map((i: any) =>
          <li key={i.line} className="px-4 py-2"><b>Linha {i.line}:</b> {i.reason}{i.raw && <span className="text-slate-500"> ({i.raw})</span>}</li>)}</ul>
      </div>}

      <form onSubmit={save} className="mt-6 border-t pt-5 grid sm:grid-cols-2 gap-4">
        <Field label="Cartão de crédito (opcional)" error={f.errors.credit_card_id}>
          <select className="field" value={f.data.credit_card_id} onChange={e => f.setData('credit_card_id', e.target.value)}>
            <option value="">Sem cartão definido</option>
            {cards.map((c: any) => <option key={c.id} value={c.id}>{c.name}{c.last_four ? ` · final ${c.last_four}` : ''}</option>)}
          </select>
          {!cards.length && <span className="block text-xs text-slate-500 mt-1">Nenhum cartão cadastrado. Dá para cadastrar em Cartões e importar depois.</span>}
        </Field>
        <Field label="Categoria (opcional)" error={f.errors.category_id}>
          <select className="field" value={f.data.category_id} onChange={e => f.setData('category_id', e.target.value)}>
            <option value="">Sem categoria</option>
            {categories.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </Field>
        <div className="sm:col-span-2 flex flex-wrap items-center justify-between gap-3">
          <p className="text-xs text-slate-500">Cada compra nova vira uma despesa no cartão, já dividida nas parcelas a partir do mês de início.</p>
          {errors?.file && <p className="text-red-600 text-sm">{errors.file}</p>}
          <button type="submit" className="btn-primary flex items-center gap-2 disabled:opacity-50" disabled={f.processing || !p.counts.new}>
            <Check size={18}/> {p.counts.new ? `Importar ${p.counts.new} compra(s)` : 'Nada novo para importar'}
          </button>
        </div>
      </form>
    </div>
  </div>;
}

export default function CardDebts({imports, preview, cards, categories, defaults}: any) {
  const {errors} = usePage<any>().props;
  const undo = (i: any) => {
    if (window.confirm(`Desfazer a importação de ${dataHora(i.created_at)}? Os ${i.created_count} lançamento(s) criados serão removidos (ficam registrados na auditoria).`))
      router.delete(`${BASE}/${i.id}`, {preserveScroll: true});
  };
  return <Shell title="Importar dívidas do cartão" subtitle="Envie a planilha de compras no cartão e as parcelas são lançadas mês a mês">
    {preview ? <Preview key={preview.hash} p={preview} cards={cards} categories={categories} defaults={defaults}/> : <UploadBox/>}
    {errors?.delete && <div className="bg-rose-50 text-rose-800 border border-rose-200 p-3 rounded-xl mb-5 text-sm">{errors.delete}</div>}
    <h2 className="font-bold text-lg mb-3">Importações feitas</h2>
    <div className="space-y-3">{imports.map((i: any) => <div key={i.id} className="panel p-5 flex flex-wrap gap-4 justify-between items-center">
      <div className="flex items-center gap-3 min-w-0">
        <div className="h-10 w-10 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center shrink-0"><CreditCard size={20}/></div>
        <div className="min-w-0">
          <p className="font-bold truncate">{i.file_name}</p>
          <p className="text-xs text-slate-500 mt-0.5">{dataHora(i.created_at)}{i.user_name && ` · ${i.user_name}`} · {i.created_count} compra(s) nova(s){i.skipped_count > 0 && `, ${i.skipped_count} pulada(s)`}{i.card_name && ` · ${i.card_name}`}</p>
        </div>
      </div>
      <div className="flex items-center gap-3"><span className="font-bold text-lg">{money(Number(i.total_cents))}</span>
        <button className="p-2 rounded-lg text-rose-600 hover:bg-rose-50 disabled:opacity-40 disabled:hover:bg-transparent" disabled={i.locked} title={i.locked ? 'Há parcelas marcadas como pagas' : 'Desfazer importação'} onClick={() => undo(i)}><Undo2 size={18}/></button>
      </div>
    </div>)}</div>
    {!imports.length && <div className="panel"><Empty>Nenhuma importação feita ainda.</Empty></div>}
  </Shell>;
}
