import React from 'react';
import {Link, router, useForm} from '@inertiajs/react';
import {ArrowLeft} from 'lucide-react';
import AuthCard, {FieldError} from '../Components/AuthCard';

/**
 * Esqueci minha senha (PasswordResetController).
 * Etapa "identificar": e-mail ou CPF. Etapa "confirmar" (só quando informou CPF): a pessoa digita o e-mail da conta.
 * Nenhuma etapa mostra o e-mail ou o CPF cadastrados.
 */
export default function ForgotPassword({etapa}: {etapa: 'identificar' | 'confirmar'}) {
  const ident = useForm({login: ''});
  const conf = useForm({email: ''});

  if (etapa === 'confirmar') {
    return (
      <AuthCard subtitle="Para sua segurança, confirme o e-mail cadastrado na sua conta. Se ele conferir, enviamos o link para criar uma nova senha." onSubmit={e => { e.preventDefault(); conf.post('/esqueci-senha/confirmar'); }}>
        <label htmlFor="email" className="block text-sm font-semibold mb-2">E-mail da conta</label>
        <input id="email" className="field mb-4" type="email" autoComplete="email" autoFocus value={conf.data.email} onChange={e => conf.setData('email', e.target.value)}/>
        <FieldError message={conf.errors.email}/>
        <button className="btn-primary w-full mt-2" disabled={conf.processing}>{conf.processing ? 'Enviando…' : 'Enviar link'}</button>
        <button type="button" className="mt-5 w-full flex items-center justify-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-900" onClick={() => router.post('/esqueci-senha/cancelar')}><ArrowLeft size={16}/>Usar outro e-mail ou CPF</button>
      </AuthCard>
    );
  }

  return (
    <AuthCard subtitle="Esqueceu a senha? Informe seu e-mail ou CPF para criar uma nova." onSubmit={e => { e.preventDefault(); ident.post('/esqueci-senha'); }}>
      <label htmlFor="login" className="block text-sm font-semibold mb-2">E-mail ou CPF</label>
      <input id="login" className="field mb-4" type="text" autoComplete="username" autoFocus value={ident.data.login} onChange={e => ident.setData('login', e.target.value)}/>
      <FieldError message={ident.errors.login}/>
      <button className="btn-primary w-full mt-2" disabled={ident.processing}>{ident.processing ? 'Aguarde…' : 'Continuar'}</button>
      <Link href="/login" className="mt-5 flex items-center justify-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-900"><ArrowLeft size={16}/>Voltar para o login</Link>
    </AuthCard>
  );
}
