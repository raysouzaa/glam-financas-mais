import React from 'react';
import {router} from '@inertiajs/react';
import {Link2,Link2Off,UserRound} from 'lucide-react';
import {Shell} from '../Components/Shell';

type Conta={name:string,email:string,cpf:string|null};
type Vinculo={owner_id:number,owner_name:string,person_name:string,compras:number,status:'pending'|'accepted'|'removed'};

const rotulos={pending:['Aguardando você','bg-amber-100 text-amber-800'],accepted:['Vinculado','bg-emerald-100 text-emerald-800'],removed:['Removido por você','bg-slate-100 text-slate-600']} as const;

export default function Profile({conta,vinculos}:{conta:Conta,vinculos:Vinculo[]}){
 const [enviando,setEnviando]=React.useState<number|null>(null);
 const opcoes=(id:number)=>({preserveScroll:true,onStart:()=>setEnviando(id),onFinish:()=>setEnviando(null)});
 const aceitar=(v:Vinculo)=>router.post(`/perfil/vinculos/${v.owner_id}`,{},opcoes(v.owner_id));
 const remover=(v:Vinculo)=>{if(confirm(`Remover o vínculo com ${v.owner_name}? Os lançamentos deixam de aparecer para você. ${v.owner_name} continua com eles normalmente.`))router.delete(`/perfil/vinculos/${v.owner_id}`,opcoes(v.owner_id))};
 const compras=(n:number)=>n===1?'1 lançamento':`${n} lançamentos`;
 return <Shell title="Meu perfil" subtitle="Seus dados e os vínculos com contas de outras pessoas">
  <div className="grid lg:grid-cols-5 gap-5 max-w-6xl">
   <section className="panel p-6 lg:col-span-2 self-start">
    <h2 className="font-semibold mb-5 flex items-center gap-2"><UserRound size={18} className="text-brand-700"/> Dados da conta</h2>
    <dl className="space-y-4 text-sm">
     {[['Nome',conta.name],['E-mail',conta.email],['CPF',conta.cpf||'Não informado']].map(([rotulo,valor])=><div key={rotulo}><dt className="text-slate-400 text-xs uppercase tracking-wide mb-1">{rotulo}</dt><dd className="text-slate-800 font-medium break-words">{valor}</dd></div>)}
    </dl>
   </section>
   <section className="panel p-6 lg:col-span-3">
    <h2 className="font-semibold mb-2 flex items-center gap-2"><Link2 size={18} className="text-brand-700"/> Contas vinculadas ao seu CPF</h2>
    <p className="text-sm text-slate-500 mb-5">Quando outra pessoa cadastra lançamentos no seu CPF, o pedido aparece aqui. Aceitando uma vez, tudo o que ela lançar para você passa a aparecer junto dos seus lançamentos, só para consulta.</p>
    {!vinculos.length&&<p className="text-sm text-slate-400 border border-dashed border-slate-200 rounded-xl p-5 text-center">{conta.cpf?'Ninguém cadastrou lançamentos no seu CPF até agora.':'Sua conta não tem CPF informado, então não há como vincular lançamentos a ela.'}</p>}
    <ul className="space-y-3">{vinculos.map(v=><li key={v.owner_id} className="border border-slate-200 rounded-xl p-4 flex flex-wrap items-center justify-between gap-3">
     <div className="min-w-0 text-sm">
      <p className="font-semibold text-slate-800 flex flex-wrap items-center gap-2">{v.owner_name}<span className={`text-xs font-medium rounded-full px-2 py-0.5 ${rotulos[v.status][1]}`}>{rotulos[v.status][0]}</span></p>
      <p className="text-slate-500 mt-1">Cadastrou você como <b className="text-slate-700">{v.person_name}</b> · {compras(v.compras)}</p>
     </div>
     {v.status==='accepted'
      ?<button className="btn-plain flex items-center gap-2 text-sm disabled:opacity-60" disabled={enviando===v.owner_id} onClick={()=>remover(v)}><Link2Off size={16}/> Remover vínculo</button>
      :<button className="btn-primary flex items-center gap-2 text-sm disabled:opacity-60" disabled={enviando===v.owner_id} onClick={()=>aceitar(v)}><Link2 size={16}/> {v.status==='removed'?'Vincular de novo':'Aceitar vínculo'}</button>}
    </li>)}</ul>
   </section>
  </div>
 </Shell>;
}
