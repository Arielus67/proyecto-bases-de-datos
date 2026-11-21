import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { Agent, fetch } from 'undici';

const [base, nivelesTxt, segundosTxt, salida] = process.argv.slice(2);
const niveles = nivelesTxt.split(',').map(Number);
const segundos = Number(segundosTxt);
const tipos = ['Mesero', 'Mesero', 'Cocinero', 'Cajero', 'Administrador'];

function cookies(jar, respuesta) {
    for (const c of respuesta.headers.getSetCookie()) {
        const [par] = c.split(';');
        const i = par.indexOf('=');
        jar[par.slice(0, i)] = par.slice(i + 1);
    }
}
const cabeceraCookies = jar => Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; ');

async function pedir(usuario, ruta, opciones = {}) {
    const inicio = performance.now();
    let estado = 0;
    try {
        const r = await fetch(base + ruta, {
            dispatcher: usuario.agente,
            redirect: 'manual',
            ...opciones,
            headers: { Cookie: cabeceraCookies(usuario.jar), ...(opciones.headers || {}) },
        });
        cookies(usuario.jar, r);
        estado = r.status;
        const texto = await r.text();
        return { estado, ms: performance.now() - inicio, texto, ubicacion: r.headers.get('location') };
    } catch (e) {
        return { estado: 0, ms: performance.now() - inicio, texto: '', error: e.message };
    }
}

async function entrar(usuario) {
    const pagina = await pedir(usuario, '/login');
    const token = (pagina.texto.match(/name="_token" value="([^"]+)"/) || [])[1];
    const cuerpo = new URLSearchParams({ _token: token, correo: usuario.correo, contrasena: 'secreto123' });
    const r = await pedir(usuario, '/login', { method: 'POST', body: cuerpo, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    usuario.ok = r.estado === 302 && !(r.ubicacion || '').endsWith('/login');
    usuario.inicio = new URL(r.ubicacion || '/', base).pathname;
    const inicio = await pedir(usuario, usuario.inicio);
    usuario.csrf = (inicio.texto.match(/name="csrf-token" content="([^"]+)"/) || inicio.texto.match(/name="_token" value="([^"]+)"/) || [])[1] || token;
    return r.ms + pagina.ms;
}

async function salir(usuario) {
    const pagina = await pedir(usuario, usuario.inicio || '/');
    const token = (pagina.texto.match(/name="csrf-token" content="([^"]+)"/) || pagina.texto.match(/name="_token" value="([^"]+)"/) || [])[1] || usuario.csrf || '';
    const cuerpo = new URLSearchParams({ _token: token });
    const r = await pedir(usuario, '/logout', { method: 'POST', body: cuerpo, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    usuario.salida = r.estado + ' ' + (r.ubicacion || '');
    if (process.env.DEPURAR) console.error('salir', usuario.correo, usuario.salida, 'pagina', pagina.estado, usuario.inicio, 'token', token.slice(0, 6), 'galletas', Object.keys(usuario.jar).join(','));
}

function rutaPara(usuario, n) {
    const ajax = { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } };
    switch (usuario.tipo) {
        case 'Mesero': return n % 5 === 0 ? ['/mesero', {}] : ['/mesero', ajax];
        case 'Cocinero': return n % 5 === 0 ? ['/cocina', {}] : ['/cocina', ajax];
        case 'Cajero': return n % 5 === 0 ? ['/caja', {}] : ['/caja', ajax];
        default: return [['/admin', '/admin/productos', '/admin/pedidos', '/admin/facturas', '/admin/roles'][n % 5], {}];
    }
}

const nucleos = Number(execSync('nproc').toString().trim());
const ticksPorSegundo = Number(execSync('getconf CLK_TCK').toString().trim());
let anterior = null;

function muestrearRecursos(muestras) {
    try {
        const pids = execSync("pgrep -f 'php.*(-S|artisan serve)' || true").toString().trim().split('\n').filter(Boolean);
        let ticks = 0, rss = 0;
        for (const pid of pids) {
            try {
                const campos = readFileSync(`/proc/${pid}/stat`, 'utf8').split(') ')[1].split(' ');
                ticks += Number(campos[11]) + Number(campos[12]) + Number(campos[13]) + Number(campos[14]);
                rss += Number(campos[21]) * 4096;
            } catch { }
        }
        const ahora = { t: performance.now(), ticks };
        if (anterior) {
            const segundosCpu = (ahora.ticks - anterior.ticks) / ticksPorSegundo;
            const pared = (ahora.t - anterior.t) / 1000;
            muestras.push({ cpu: Math.max(0, 100 * segundosCpu / pared / nucleos), rssMb: rss / 1048576 });
        }
        anterior = ahora;
    } catch { }
}

const percentil = (v, p) => { if (!v.length) return 0; const o = [...v].sort((a, b) => a - b); return o[Math.min(o.length - 1, Math.floor(p / 100 * o.length))]; };
const resultados = [];

for (const n of niveles) {
    const usuarios = Array.from({ length: n }, (_, i) => ({ correo: `carga${String(i + 1).padStart(3, '0')}@prueba.test`, tipo: tipos[(i + 1) % 5], jar: {}, agente: new Agent({ localAddress: `127.0.${Math.floor((i + 2) / 250)}.${(i + 2) % 250}` }) }));
    const tiemposLogin = await Promise.all(usuarios.map(entrar));
    const fallidosLogin = usuarios.filter(u => !u.ok).length;
    const muestras = [];
    anterior = null;
    const reloj = setInterval(() => muestrearRecursos(muestras), 1000);
    const registros = [];
    const fin = performance.now() + segundos * 1000;
    await Promise.all(usuarios.map(async (u, i) => {
        let k = i;
        while (performance.now() < fin) {
            const [ruta, opciones] = rutaPara(u, k++);
            const r = await pedir(u, ruta, opciones);
            registros.push({ ruta, estado: r.estado, ms: r.ms, tipo: u.tipo, correo: u.correo, ubicacion: r.ubicacion });
        }
    }));
    clearInterval(reloj);
    await Promise.all(usuarios.map(salir));
    const tiempos = registros.map(r => r.ms);
    if (process.env.DEPURAR) { const m = {}; for (const r of registros) if (r.estado !== 200) { const k = r.tipo + ' ' + r.ruta + ' -> ' + r.ubicacion; m[k] = (m[k] || 0) + 1; } console.error(JSON.stringify(m)); console.error(JSON.stringify(usuarios.map(u => [u.correo, u.ok]))); }
    const ok = registros.filter(r => r.estado === 200).length;
    const porCodigo = {};
    for (const r of registros) if (r.estado !== 200) porCodigo[r.estado] = (porCodigo[r.estado] || 0) + 1;
    const porRuta = {};
    for (const r of registros) { (porRuta[r.ruta] ||= []).push(r.ms); }
    const resumen = {
        usuarios: n,
        segundos,
        login_fallidos: fallidosLogin,
        login_promedio_ms: Math.round(tiemposLogin.reduce((a, b) => a + b, 0) / n),
        solicitudes: registros.length,
        exitosas: ok,
        fallidas: registros.length - ok,
        errores_http: porCodigo,
        promedio_ms: Math.round(tiempos.reduce((a, b) => a + b, 0) / Math.max(1, tiempos.length)),
        p95_ms: Math.round(percentil(tiempos, 95)),
        maximo_ms: Math.round(Math.max(0, ...tiempos)),
        rendimiento_rps: +(registros.length / segundos).toFixed(1),
        cpu_promedio_pct: Math.round(muestras.reduce((a, m) => a + m.cpu, 0) / Math.max(1, muestras.length)),
        cpu_maximo_pct: Math.round(Math.max(0, ...muestras.map(m => m.cpu))),
        memoria_max_mb: Math.round(Math.max(0, ...muestras.map(m => m.rssMb))),
        mas_lentas: Object.entries(porRuta).map(([ruta, v]) => ({ ruta, promedio_ms: Math.round(v.reduce((a, b) => a + b, 0) / v.length), p95_ms: Math.round(percentil(v, 95)), n: v.length })).sort((a, b) => b.promedio_ms - a.promedio_ms).slice(0, 3),
    };
    resultados.push(resumen);
    console.log(JSON.stringify(resumen));
}
writeFileSync(salida, JSON.stringify(resultados, null, 2));
