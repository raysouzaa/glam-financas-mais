import React from 'react';
import {Link, router, useForm, usePage} from '@inertiajs/react';
import {MailCheck, RefreshCw, UserPlus} from 'lucide-react';
import AuthCard from '../Components/AuthCard';

type Verify = {name?: string; email: string; minutes?: number};

/** Cartão exibido quando a senha está certa, mas o e-mail da conta ainda não foi confirmado. */
function VerifyNotice({verify, onResend, resending, onClose}: {verify: Verify; onResend: () => void; resending: boolean; onClose: () => void}) {
  return (
    <div role="status" className="rounded-2xl border border-sky-200 bg-sky-50 p-5 mb-5 text-sm text-slate-700">
      <div className="flex gap-3">
        <span className="h-10 w-10 shrink-0 rounded-full bg-white text-brand-700 flex items-center justify-center shadow-sm"><MailCheck size={20}/></span>
        <div>
          <p className="font-bold text-slate-900">Quase lá{verify.name ? `, ${verify.name}` : ''}! 💌</p>
          <p className="mt-1">Sua senha está certinha. Só falta confirmar o seu e-mail: enviamos um link para <b className="break-all">{verify.email}</b>.</p>
        </div>
      </div>
      <ul className="mt-4 space-y-1.5 text-slate-600">
        <li>• Abra o e-mail e clique em <b>“Confirmar minha conta”</b>.</li>
        <li>• Não achou? Dê uma olhada no <b>spam</b> ou em <b>promoções</b>.</li>
        {verify.minutes ? <li>• O link vale por {verify.minutes} minutos. Se expirou, é só pedir outro.</li> : null}
      </ul>
      <div className="mt-4 flex flex-wrap items-center gap-3">
        <button type="button" className="btn-primary text-sm flex items-center gap-2 disabled:opacity-60" onClick={onResend} disabled={resending}>
          <RefreshCw size={16} className={resending ? 'animate-spin' : ''}/>{resending ? 'Enviando…' : 'Reenviar o link'}
        </button>
        <button type="button" className="text-sm text-slate-500 hover:text-slate-800 underline" onClick={onClose}>Entrar com outra conta</button>
      </div>
    </div>
  );
}

export default function Login() {
  const props = usePage().props as any;
  const registrationEnabled = !!props.registrationEnabled;
  const f = useForm({email: '', password: ''});
  const errors = f.errors as Record<string, string | undefined>;
  const [resending, setResending] = React.useState(false);
  // Guarda o aviso localmente para ele continuar visível depois de reenviar o link.
  const [verify, setVerify] = React.useState<Verify | null>(props.flash?.verify || null);

  React.useEffect(() => {
    if (props.flash?.verify) {
      setVerify(props.flash.verify);
      f.setData('email', props.flash.verify.email);
    }
  }, [props.flash?.verify]);

  const resend = () => {
    if (!verify) return;
    router.post('/email/reenviar', {email: verify.email}, {
      preserveScroll: true,
      preserveState: true,
      onStart: () => setResending(true),
      onFinish: () => setResending(false),
    });
  };

  return (
    <AuthCard subtitle="Acesse seu painel financeiro." onSubmit={e => { e.preventDefault(); f.post('/login', {onFinish: () => f.reset('password')}); }}>
      {verify && <VerifyNotice verify={verify} onResend={resend} resending={resending} onClose={() => { setVerify(null); f.reset(); }}/>}
      <label htmlFor="email" className="block text-sm font-semibold mb-2">E-mail</label>
      <input id="email" className="field mb-4" type="email" required autoComplete="email" value={f.data.email} onChange={e => f.setData('email', e.target.value)}/>
      <label htmlFor="password" className="block text-sm font-semibold mb-2">Senha</label>
      <input id="password" className="field mb-2" type="password" required autoComplete="current-password" value={f.data.password} onChange={e => f.setData('password', e.target.value)}/>
      {errors.email && <p className="text-red-600 text-sm my-2">{errors.email}</p>}
      <button className="btn-primary w-full mt-5" disabled={f.processing}>Entrar no sistema</button>
      {registrationEnabled && (
        <>
          <div className="flex items-center gap-3 my-6 text-xs text-slate-400"><span className="h-px flex-1 bg-slate-200"/>ou<span className="h-px flex-1 bg-slate-200"/></div>
          <Link href="/criar-conta" className="btn-plain w-full flex items-center justify-center gap-2"><UserPlus size={18}/>Criar conta</Link>
        </>
      )}
      <p className="text-slate-400 text-xs mt-6 text-center">Acesso restrito aos usuários autorizados.</p>
    </AuthCard>
  );
}
