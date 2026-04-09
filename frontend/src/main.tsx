import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'

// shadcn uses .dark class — sync with OS preference
const applyDark = (dark: boolean) => document.documentElement.classList.toggle('dark', dark)
const mq = window.matchMedia('(prefers-color-scheme: dark)')
applyDark(mq.matches)
mq.addEventListener('change', (e) => applyDark(e.matches))

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
