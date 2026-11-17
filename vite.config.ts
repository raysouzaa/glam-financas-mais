import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
export default defineConfig({plugins:[react()],build:{manifest:'manifest.json',outDir:'public/build',rollupOptions:{input:['resources/css/app.css','resources/js/app.tsx']}},server:{host:'0.0.0.0',port:5173,strictPort:true,hmr:{host:'localhost'}}});
