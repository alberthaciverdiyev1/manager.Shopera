import './app.css';
import htmx from 'htmx.org';
import 'flowbite';

declare global {
    interface Window {
        htmx: typeof htmx;
        Manager: { toast: (type: string, message: string) => void };
    }
}

window.htmx = htmx;

function toast(type: string, message: string): void {
    const root = document.getElementById('toast-root');
    if (!root || !message) return;

    const tone: Record<string, string> = { success: 'bg-emerald-600', error: 'bg-rose-600', info: 'bg-gray-800' };
    const el = document.createElement('div');
    el.className = `pointer-events-auto rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg ${tone[type] ?? tone.info}`;
    el.textContent = message;
    el.style.opacity = '0';
    root.appendChild(el);

    requestAnimationFrame(() => {
        el.style.transition = 'all .25s ease';
        el.style.opacity = '1';
    });
    setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 300);
    }, 3200);
}

window.Manager = { toast };

document.addEventListener('toast', (event) => {
    const detail = (event as CustomEvent<{ type: string; message: string }>).detail;
    if (detail) toast(detail.type, detail.message);
});

function closeModal(): void {
    const root = document.getElementById('modal-root');
    if (root) root.innerHTML = '';
}

document.body.addEventListener('modal:close', closeModal);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
});

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.getElementById('manager-shell');
    const toggle = document.getElementById('sidebar-toggle');
    if (shell && localStorage.getItem('manager:sidebar') === 'collapsed') shell.classList.add('sidebar-collapsed');
    toggle?.addEventListener('click', () => {
        if (!shell) return;
        shell.classList.toggle('sidebar-collapsed');
        localStorage.setItem('manager:sidebar', shell.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open');
    });
});
