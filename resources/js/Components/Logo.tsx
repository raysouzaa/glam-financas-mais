import React from 'react';

/**
 * Logo do GlamFinanças (arquivos em public/brand/).
 * - horizontal: símbolo + nome lado a lado (menu lateral, login, criar conta)
 * - vertical:   símbolo em cima do nome
 * - simbolo:    só o "GF" com o diamante
 * Cores da marca: azul #1E4FD8, azul claro #5AA9FF, marinho #0B1F3A.
 */
const ARQUIVOS = {
  horizontal: {src: '/brand/logo-horizontal.png', w: 1340, h: 256},
  vertical: {src: '/brand/logo-vertical.png', w: 504, h: 296},
  simbolo: {src: '/brand/simbolo.png', w: 328, h: 256},
};

export default function Logo({variante = 'horizontal', className = 'h-10 w-auto'}: {variante?: keyof typeof ARQUIVOS; className?: string}) {
  const a = ARQUIVOS[variante];
  return <img src={a.src} width={a.w} height={a.h} alt="GlamFinanças" className={className}/>;
}
