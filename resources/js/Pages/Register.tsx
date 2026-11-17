import React from 'react';
import {Link, useForm} from '@inertiajs/react';
import {ArrowLeft} from 'lucide-react';
import AuthCard, {FieldError, PasswordInput, REQUISITOS_SENHA, RequisitosSenha} from '../Components/AuthCard';
import {formatCpf} from '../Components/Shell';

// Mesma conta de app/Support/Cpf.php (o servidor confere de novo).
const cpfValido = (v: string) => {
  const c = v.replace(/\D/g, '');
  if (c.length !== 11 || /^(\d)\1{10}$/.test(c)) return false;
  for (let t = 9; t < 11; t++) {
    let soma = 0;
    for (let i = 0; i < t; i++) soma += Number(c[i]) * (t + 1 - i);
    if (Number(c[t]) !== ((10 * soma) % 11) % 10) return false;
  }
  return true;
};

export default function Register() {
  const f = useForm({name: '', email: '', cpf: '', password: '', password_confirmation: ''});
  const [local, setLocal] = React.useState<Record<string, string>>({});
  const errors = {...f.errors, ...local} as Record<string, string | undefined>;

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const v: Record<string, string> = {};
    if (f.data.name.trim().length < 3) v.name = 'Informe seu nome completo.';
    if (!/^\S+@\S+\.\S+$/.test(f.data.email.trim())) v.email = 'Informe um e-mail válido.';
    if (!cpfValido(f.data.cpf)) v.cpf = 'Informe um CPF válido.';
    if (REQUISITOS_SENHA.some(r => !r.ok(f.data.password))) v.password = 'A senha ainda não cumpre todos os requisitos acima.';
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

      <label htmlFor="cpf" className="block text-sm font-semibold mb-2">CPF</label>
      <input id="cpf" className="field mb-1" type="text" inputMode="numeric" autoComplete="off" placeholder="000.000.000-00" value={formatCpf(f.data.cpf)} onChange={e => { f.setData('cpf', e.target.value.replace(/\D/g, '').slice(0, 11)); if (local.cpf) setLocal(({cpf: _, ...rest}) => rest); }}/>
      <p className="text-xs text-slate-400 mb-4">Usamos o CPF para vincular sua conta às suas dívidas cadastradas.</p>
      <FieldError message={errors.cpf}/>

      <label htmlFor="password" className="block text-sm font-semibold mb-2">Senha</label>
      <PasswordInput id="password" className="mb-1" autoComplete="new-password" value={f.data.password} onChange={set('password')}/>
      <RequisitosSenha senha={f.data.password}/>
      <FieldError message={errors.password}/>

      <label htmlFor="password_confirmation" className="block text-sm font-semibold mb-2">Confirme a senha</label>
      <PasswordInput id="password_confirmation" autoComplete="new-password" value={f.data.password_confirmation} onChange={set('password_confirmation')}/>
      <FieldError message={errors.password_confirmation}/>

      <button className="btn-primary w-full mt-2" disabled={f.processing}>{f.processing ? 'Criando conta…' : 'Criar conta'}</button>
      <Link href="/login" className="mt-5 flex items-center justify-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-900"><ArrowLeft size={16}/>Já tenho conta, quero entrar</Link>
    </AuthCard>
  );
}
