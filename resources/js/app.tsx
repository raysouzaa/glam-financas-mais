import React from 'react';
import {createRoot} from 'react-dom/client';
import {createInertiaApp} from '@inertiajs/react';
import Dashboard from './Pages/Dashboard';
import Login from './Pages/Login';
import Manage from './Pages/Manage';
import Audit from './Pages/Audit';
createInertiaApp({resolve:(name)=>({Dashboard,Login,Manage,Audit} as Record<string,any>)[name],setup({el,App,props}){createRoot(el).render(<App {...props}/>);},progress:{color:'#2d7abe'}});
