import React from 'react';
import {usePage} from '@inertiajs/react';
import {Eye, EyeOff} from 'lucide-react';
import Logo from './Logo';

/** Moldura das telas públicas (login e criar conta), com as mensagens de flash da sessão. */
export default function AuthCard({subtitle, onSubmit, children}: {subtitle: string; onSubmit: (e: React.FormEvent) => void; children: React.ReactNode}) {
  const flash = ((usePage().props as any).flash || {}) as {success?: string; error?: string};
  return (
    <div className="min-h-screen bg-gradient-to-br from-brand-900 via-brand-700 to-sky-500 flex justify-center items-center p-4">
      <form className="w-full max-w-md rounded-3xl bg-white p-8 sm:p-10 shadow-2xl" onSubmit={onSubmit} noValidate>
        <h1 className="mb-1"><Logo className="h-11 w-auto max-w-full"/></h1>
        <p className="text-slate-500 mt-2 mb-6">{subtitle}</p>
        {flash.success && <div role="status" className="bg-emerald-50 text-emerald-800 border border-emerald-200 p-3 rounded-xl mb-5 text-sm">{flash.success}</div>}
        {flash.error && <div role="alert" className="bg-rose-50 text-rose-800 border border-rose-200 p-3 rounded-xl mb-5 text-sm">{flash.error}</div>}
        {children}
      </form>
    </div>
  );
}

export function FieldError({message}: {message?: string}) {
  return message ? <p className="text-red-600 text-sm -mt-2 mb-3">{message}</p> : null;
}

/** Campo de senha com o ícone de olho para mostrar/ocultar o que foi digitado. */
export function PasswordInput({id, value, onChange, autoComplete, className = 'mb-4', autoFocus}: {id: string; value: string; onChange: (e: React.ChangeEvent<HTMLInputElement>) => void; autoComplete: string; className?: string; autoFocus?: boolean}) {
  const [show, setShow] = React.useState(false);
  return (
    <div className={`relative ${className}`}>
      <input id={id} className="field pr-11" type={show ? 'text' : 'password'} autoComplete={autoComplete} autoFocus={autoFocus} value={value} onChange={onChange}/>
      <button type="button" className="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600" onClick={() => setShow(s => !s)} aria-label={show ? 'Ocultar senha' : 'Mostrar senha'} title={show ? 'Ocultar senha' : 'Mostrar senha'}>
        {show ? <EyeOff size={18}/> : <Eye size={18}/>}
      </button>
    </div>
  );
}

// Mesmos requisitos de app/Support/RegraSenha.php (o servidor sempre confere de novo).
const MIN = 6;
export const REQUISITOS_SENHA: {texto: string; ok: (s: string) => boolean}[] = [
  {texto: `Pelo menos ${MIN} caracteres`, ok: s => [...s].length >= MIN},
  {texto: 'Uma letra maiúscula', ok: s => /\p{Lu}/u.test(s)},
  {texto: 'Uma letra minúscula', ok: s => /\p{Ll}/u.test(s)},
  {texto: 'Um número', ok: s => /\d/.test(s)},
];

/** Checklist dos requisitos da senha, marcando em verde o que já foi cumprido. */
export function RequisitosSenha({senha}: {senha: string}) {
  return (
    <ul className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs mt-2 mb-4" aria-label="Requisitos da senha">
      {REQUISITOS_SENHA.map(r => {
        const ok = r.ok(senha);
        return <li key={r.texto} className={ok ? 'text-emerald-600' : 'text-slate-400'}>{ok ? '✓' : '○'} {r.texto}</li>;
      })}
    </ul>
  );
}
