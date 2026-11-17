import React from 'react';
import {usePage} from '@inertiajs/react';
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
