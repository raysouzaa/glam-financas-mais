import React from 'react';
import {Link, useForm} from '@inertiajs/react';
import {ArrowLeft, Eye, EyeOff} from 'lucide-react';
import AuthCard, {FieldError} from '../Components/AuthCard';

// Mesmos requisitos de app/Support/RegraSenha.php (o servidor sempre confere de novo).
const MIN = 6;
const REQUISITOS: {texto: string; ok: (s: string) => boolean}[] = [
  {texto: `Pelo menos ${MIN} caracteres`, ok: s => [...s].length >= MIN},
  {texto: 'Uma letra maiúscula', ok: s => /\p{Lu}/u.test(s)},
  {texto: 'Uma letra minúscula', ok: s => /\p{Ll}/u.test(s)},
  {texto: 'Um número', ok: s => /\d/.test(s)},
];

export default function Register() {
  const f = useForm({name: '', email: '', password: '', password_confirmation: ''});
  const [show, setShow] = React.useState(false);
  const [local, setLocal] = React.useState<Record<string, string>>({});
  const errors = {...f.errors, ...local} as Record<string, string | undefined>;

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const v: Record<string, string> = {};
    if (f.data.name.trim().length < 3) v.name = 'Informe seu nome completo.';
    if (!/^\S+@\S+\.\S+$/.test(f.data.email.trim())) v.email = 'Informe um e-mail válido.';
    if (REQUISITOS.some(r => !r.ok(f.data.password))) v.password = 'A senha ainda não cumpre todos os requisitos acima.';
    else if (f.data.password !== f.data.password_confirmation) v.password_confirmation = 'As senhas não conferem.';
    setLocal(v);
    if (Object.keys(v).length) return;
    f.post('/criar-conta', {onError: () => f.reset('password', 'password_confirmation')});
  };

  const set = (k: keyof typeof f.data) => (e: React.ChangeEvent<HTMLInputElement>) => {
    f.setData(k, e.target.value);
    if (local[k]) setLocal(({[k]: _, ...rest}) => rest);
  };

  return (
    <AuthCard subtitle="Crie sua conta. Depois é só confirmar o e-mail que vamos enviar." onSubmit={submit}>
      <label htmlFor="name" className="block text-sm font-semibold mb-2">Nome completo</label>
      <input id="name" className="field mb-4" type="text" autoComplete="name" autoFocus value={f.data.name} onChange={set('name')}/>
      <FieldError message={errors.name}/>

      <label htmlFor="email" className="block text-sm font-semibold mb-2">E-mail</label>
      <input id="email" className="field mb-4" type="email" autoComplete="email" value={f.data.email} onChange={set('email')}/>
      <FieldError message={errors.email}/>

      <label htmlFor="password" className="block text-sm font-semibold mb-2">Senha</label>
      <div className="relative mb-1">
        <input id="password" className="field pr-11" type={show ? 'text' : 'password'} autoComplete="new-password" value={f.data.password} onChange={set('password')}/>
        <button type="button" className="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600" onClick={() => setShow(s => !s)} aria-label={show ? 'Ocultar senha' : 'Mostrar senha'}>
          {show ? <EyeOff size={18}/> : <Eye size={18}/>}
        </button>
      </div>
      <ul className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs mt-2 mb-4" aria-label="Requisitos da senha">
        {REQUISITOS.map(r => {
          const ok = r.ok(f.data.password);
          return <li key={r.texto} className={ok ? 'text-emerald-600' : 'text-slate-400'}>{ok ? '✓' : '○'} {r.texto}</li>;
        })}
      </ul>
      <FieldError message={errors.password}/>

      <label htmlFor="password_confirmation" className="block text-sm font-semibold mb-2">Confirme a senha</label>
      <input id="password_confirmation" className="field mb-4" type={show ? 'text' : 'password'} autoComplete="new-password" value={f.data.password_confirmation} onChange={set('password_confirmation')}/>
      <FieldError message={errors.password_confirmation}/>

      <button className="btn-primary w-full mt-2" disabled={f.processing}>{f.processing ? 'Criando conta…' : 'Criar conta'}</button>
      <Link href="/login" className="mt-5 flex items-center justify-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-900"><ArrowLeft size={16}/>Já tenho conta, quero entrar</Link>
    </AuthCard>
  );
}
