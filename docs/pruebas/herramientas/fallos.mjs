import { Agent, fetch } from 'undici';
import { execFileSync, execSync, spawn } from 'node:child_process';
import { renameSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const [base, bd, appPublic, salida] = process.argv.slice(2);
const ayudante = fileURLToPath(new URL('./consulta.php', import.meta.url));
const sql = c => JSON.parse(execFileSync('php', [ayudante, bd, c]).toString());
const resultados = [];
const anotar = (prueba, esperado, obtenido, ok) => { resultados.push({ prueba, esperado, obtenido, ok }); console.log(ok ? 'PASA ' : 'FALLA', prueba, '|', obtenido); };
const esperar = ms => new Promise(r => setTimeout(r, ms));

function cliente(ip) {
    const jar = {}; const agente = new Agent({ localAddress: ip });
    const pedir = async (ruta, op = {}) => {
        const t0 = performance.now();
        try {
            const r = await fetch(base + ruta, { dispatcher: agente, redirect: 'manual', ...op, headers: { Cookie: Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; '), ...(op.headers || {}) } });
            for (const c of r.headers.getSetCookie()) { const [p] = c.split(';'); const i = p.indexOf('='); jar[p.slice(0, i)] = p.slice(i + 1); }
            return { s: r.status, l: r.headers.get('location') || '', t: await r.text(), ms: Math.round(performance.now() - t0) };
        } catch (e) { return { s: 0, t: e.cause?.code || e.message, ms: Math.round(performance.now() - t0) }; }
    };
    const token = t => (t.match(/name="csrf-token" content="([^"]+)"/) || t.match(/name="_token" value="([^"]+)"/) || [])[1];
    return { pedir, token, async entrar(correo) { const p = await pedir('/login'); await pedir('/login', { method: 'POST', body: new URLSearchParams({ _token: token(p.t), correo, contrasena: 'secreto123' }), headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }); this.csrf = token((await pedir('/admin/roles')).t); },
        crearRol(nombre) { const f = new URLSearchParams({ _token: this.csrf, nombre_rol: nombre }); f.append('permisos[]', 'pedidos.ver'); f.append('permisos[]', 'facturas.ver'); return pedir('/admin/roles', { method: 'POST', body: f, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }); } };
}

function iniciarServidor() {
    const p = spawn('php', ['-d', 'opcache.enable_cli=1', '-S', '127.0.0.1:8125', '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], { cwd: appPublic, env: { ...process.env, E2E_SQLITE: bd, PHP_CLI_SERVER_WORKERS: '4' }, detached: true, stdio: 'ignore' });
    p.unref();
    return esperar(1500);
}
const detenerServidor = () => { try { execSync("pkill -9 -f 'php -d opcache.enable_cli=1 -S 127.0.0.1:8125'"); } catch { } };

const admin = cliente('127.0.8.1');
await admin.entrar('carga004@prueba.test');
anotar('Preparación: sesión de administrador', '200', String((await admin.pedir('/admin/roles')).s), (await admin.pedir('/admin/roles')).s === 200);

renameSync(bd, bd + '.oculta');
const sinBd = await admin.pedir('/admin/productos');
anotar('Base de datos no disponible: la petición falla de forma controlada', '500 sin detalles internos', `${sinBd.s} en ${sinBd.ms} ms, muestra ruta/SQL: ${/SQLSTATE|\/tmp\/|PDO/.test(sinBd.t) ? 'sí' : 'no'}`, sinBd.s === 500 && !/SQLSTATE|\/tmp\/|PDO/.test(sinBd.t));
renameSync(bd + '.oculta', bd);
const vuelta = await admin.pedir('/admin/productos');
anotar('Base de datos no disponible: se recupera sola al volver', '200', `${vuelta.s} en ${vuelta.ms} ms`, vuelta.s === 200);

const bloqueo = spawn('python3', ['-c', `import sqlite3,time\nc=sqlite3.connect('${bd}',isolation_level=None)\nc.execute('BEGIN EXCLUSIVE')\ntime.sleep(6)\nc.execute('COMMIT')`]);
const finBloqueo = new Promise(r => bloqueo.on('exit', r));
await esperar(500);
const conBloqueo = await admin.crearRol('Rol durante bloqueo');
await finBloqueo;
anotar('Base de datos bloqueada 6 s por otro proceso (equivale a una consulta lenta)', 'espera y termina sin error ni datos a medias', `${conBloqueo.s} en ${conBloqueo.ms} ms; rol creado=${sql("select count(*) from ROL where nombre_rol='Rol durante bloqueo'")[0][0]}`, conBloqueo.s === 302 && sql("select count(*) from ROL where nombre_rol='Rol durante bloqueo'")[0][0] === 1);

const antes = sql('select count(*) from ROL')[0][0];
let enviados = 0, errores = 0;
const lote = (async () => { for (let i = 0; i < 400; i++) { const r = await admin.crearRol(`Rol caida ${i}`); enviados++; if (r.s !== 302) errores++; } })();
await esperar(1200);
detenerServidor();
await lote;
const integridad = sql('pragma integrity_check')[0][0];
const sinPermisos = sql('select count(*) from ROL r where not exists (select 1 from ROL_PERMISO rp where rp.id_rol = r.id_rol)')[0][0];
const creados = sql('select count(*) from ROL')[0][0] - antes;
anotar('Caída abrupta de Laravel (kill -9) mientras se crean roles', 'integridad ok y ningún rol a medias', `creados antes de caer=${creados}, peticiones fallidas tras caer=${errores}, integrity_check=${integridad}, roles sin permisos=${sinPermisos}`, integridad === 'ok' && sinPermisos === 0);
await iniciarServidor();
const tras = cliente('127.0.8.2');
await tras.entrar('carga009@prueba.test');
const r = await tras.crearRol('Rol tras reinicio');
anotar('Reinicio del servidor: el sistema vuelve a operar', '302 y rol creado', `${r.s}; rol=${sql("select count(*) from ROL where nombre_rol='Rol tras reinicio'")[0][0]}`, r.s === 302);

writeFileSync(salida, JSON.stringify(resultados, null, 2));
console.log(`\n${resultados.filter(x => x.ok).length}/${resultados.length} pruebas aprobadas`);
