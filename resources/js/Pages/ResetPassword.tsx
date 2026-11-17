import React from 'react';
import {Link, useForm} from '@inertiajs/react';
import {ArrowLeft} from 'lucide-react';
import AuthCard, {FieldError, PasswordInput, REQUISITOS_SENHA, RequisitosSenha} from '../Components/AuthCard';

/** Tela aberta pelo link do e-mail de redefinição. O e-mail vem no link e não é exibido. */
export default function ResetPassword({token, email}: {token: string; email: string}) {
  const f = useForm({token, email, password: '', password_confirmation: ''});
  const [local, setLocal] = React.useState<Record<string, string>>({});
  const errors = {...f.errors, ...local} as Record<string, string | undefined>;

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const v: Record<string, string> = {};
    if (REQUISITOS_SENHA.some(r => !r.ok(f.data.password))) v.password = 'A senha ainda não cumpre todos os requisitos acima.';
    else if (f.data.password !== f.data.password_confirmation) v.password_confirmation = 'As senhas não conferem.';
    setLocal(v);
    if (Object.keys(v).length) return;
    f.post('/redefinir-senha', {onError: () => f.reset('password', 'password_confirmation')});
  };

  return (
    <AuthCard subtitle="Crie uma nova senha para a sua conta." onSubmit={submit}>
      <label htmlFor="password" className="block text-sm font-semibold mb-2">Nova senha</label>
      <PasswordInput id="password" className="mb-1" autoComplete="new-password" autoFocus value={f.data.password} onChange={e => { f.setData('password', e.target.value); setLocal({}); }}/>
      <RequisitosSenha senha={f.data.password}/>
      <FieldError message={errors.password || errors.email || errors.token}/>

      <label htmlFor="password_confirmation" className="block text-sm font-semibold mb-2">Confirme a nova senha</label>
      <PasswordInput id="password_confirmation" autoComplete="new-password" value={f.data.password_confirmation} onChange={e => { f.setData('password_confirmation', e.target.value); setLocal({}); }}/>
      <FieldError message={errors.password_confirmation}/>

      <button className="btn-primary w-full mt-2" disabled={f.processing}>{f.processing ? 'Salvando…' : 'Salvar nova senha'}</button>
      <Link href="/login" className="mt-5 flex items-center justify-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-900"><ArrowLeft size={16}/>Voltar para o login</Link>
    </AuthCard>
  );
}
