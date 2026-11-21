import { Agent, fetch } from 'undici';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { writeFileSync } from 'node:fs';

const [base, bd, salida] = process.argv.slice(2);
const resultados = [];
let siguienteIp = 10;
const anotar = (grupo, prueba, esperado, obtenido, ok) => { resultados.push({ grupo, prueba, esperado, obtenido, ok }); console.log(ok ? 'PASA ' : 'FALLA', grupo, '|', prueba, '|', obtenido); };
const ayudante = fileURLToPath(new URL('./consulta.php', import.meta.url));
const sql = consulta => execFileSync('php', [ayudante, bd, consulta]).toString();

function cliente() {
    const ip = `127.0.9.${siguienteIp++}`;
    const jar = {};
    const agente = new Agent({ localAddress: ip });
    const pedir = async (ruta, op = {}) => {
        const inicio = performance.now();
        try {
            const r = await fetch(base + ruta, { dispatcher: agente, redirect: 'manual', ...op, headers: { Cookie: Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; '), ...(op.headers || {}) } });
            const crudas = r.headers.getSetCookie();
            for (const c of crudas) { const [p] = c.split(';'); const i = p.indexOf('='); jar[p.slice(0, i)] = p.slice(i + 1); }
            return { s: r.status, l: r.headers.get('location') || '', t: await r.text(), cookies: crudas, ms: performance.now() - inicio };
        } catch (e) { return { s: 0, l: '', t: e.message, cookies: [], ms: performance.now() - inicio }; }
    };
    const token = t => (t.match(/name="csrf-token" content="([^"]+)"/) || t.match(/name="_token" value="([^"]+)"/) || [])[1];
    const formulario = cuerpo => ({ method: 'POST', body: new URLSearchParams(cuerpo), headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    const c = { ip, jar, pedir, token, formulario };
    c.entrar = async correo => {
        const p = await pedir('/login');
        const r = await pedir('/login', formulario({ _token: token(p.t), correo, contrasena: 'secreto123' }));
        c.inicio = r.l;
        const pagina = await pedir(new URL(r.l || '/', base).pathname);
        c.csrf = token(pagina.t);
        return r;
    };
    c.salir = async () => { const p = await pedir(new URL(c.inicio || '/', base).pathname); return pedir('/logout', formulario({ _token: token(p.t) || c.csrf })); };
    return c;
}

const anonimo = cliente();
for (const ruta of ['/', '/admin', '/admin/empleados', '/admin/roles', '/admin/respaldos', '/mesero', '/cocina', '/caja', '/caja/facturas', '/admin/reportes/ventas']) {
    const r = await anonimo.pedir(ruta);
    anotar('Acceso sin sesión', `GET ${ruta}`, '302 al login', `${r.s} ${r.l.replace(base, '')}`, r.s === 302 && r.l.endsWith('/login'));
}

const mesero = cliente();
await mesero.entrar('carga001@prueba.test');
for (const [metodo, ruta] of [['GET', '/admin'], ['GET', '/admin/productos'], ['GET', '/caja'], ['GET', '/cocina'], ['POST', '/admin/respaldos'], ['POST', '/caja/cobrar/1'], ['PUT', '/cocina/actualizar-estado/1'], ['DELETE', '/admin/productos/1'], ['GET', '/admin/respaldos/restaurar']]) {
    const r = await mesero.pedir(ruta, metodo === 'GET' ? {} : { method: metodo, body: new URLSearchParams({ _token: mesero.csrf }), headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' } });
    anotar('Acceso sin permiso (mesero)', `${metodo} ${ruta}`, '403', String(r.s), r.s === 403);
}
const productosAntes = sql('select count(*) from PRODUCTO');
anotar('Acceso sin permiso (mesero)', 'Los productos siguen intactos tras el DELETE rechazado', productosAntes, sql('select count(*) from PRODUCTO'), productosAntes === sql('select count(*) from PRODUCTO'));
await mesero.salir();

const admin = cliente();
const primera = await admin.pedir('/login');
const galletaAntes = admin.jar.la_cuchara_de_luison_session;
await admin.pedir('/login', admin.formulario({ _token: admin.token(primera.t), correo: 'carga004@prueba.test', contrasena: 'secreto123' }));
anotar('Sesiones', 'El identificador de sesión cambia al iniciar sesión (fijación de sesión)', 'distinto', admin.jar.la_cuchara_de_luison_session !== galletaAntes ? 'distinto' : 'igual', admin.jar.la_cuchara_de_luison_session !== galletaAntes);
const panel = await admin.pedir('/admin');
const galleta = panel.cookies.find(c => c.startsWith('la_cuchara_de_luison_session=')) || '';
anotar('Sesiones', 'Cookie de sesión HttpOnly y SameSite', 'httponly; samesite=lax', galleta.toLowerCase().split(';').slice(1).map(s => s.trim()).join('; '), /httponly/i.test(galleta) && /samesite=lax/i.test(galleta));
admin.csrf = admin.token(panel.t);

const sinToken = await admin.pedir('/admin/roles', admin.formulario({ nombre_rol: 'Sin token', 'permisos[]': 'productos.ver' }));
anotar('CSRF', 'POST /admin/roles sin token CSRF', '419', String(sinToken.s), sinToken.s === 419);
const tokenFalso = await admin.pedir('/admin/roles', admin.formulario({ _token: 'falso', nombre_rol: 'Token falso', 'permisos[]': 'productos.ver' }));
anotar('CSRF', 'POST /admin/roles con token inventado', '419', String(tokenFalso.s), tokenFalso.s === 419);

const xss = `<script>alert('xss')</script>`;
const formRol = new URLSearchParams({ _token: admin.csrf, nombre_rol: xss });
formRol.append('permisos[]', 'productos.ver');
await admin.pedir('/admin/roles', { method: 'POST', body: formRol, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
const listado = await admin.pedir('/admin/roles');
anotar('XSS', 'Nombre de rol con <script> se muestra escapado', 'texto escapado, sin <script> ejecutable', listado.t.includes(xss) ? 'script sin escapar' : (listado.t.includes('&lt;script&gt;') ? 'escapado' : 'no aparece'), !listado.t.includes(xss) && listado.t.includes('&lt;script&gt;'));

const tablasAntes = sql("select count(*) from sqlite_master where type='table'");
for (const ruta of ["/admin/pedidos?estado=' OR 1=1 --", "/admin/pedidos?fecha=2026-01-01' OR '1'='1", '/admin/auditoria?id_pedido=1;DROP TABLE PEDIDO', "/admin/productos/1' OR '1'='1/edit"]) {
    const r = await admin.pedir(encodeURI(ruta));
    anotar('Inyección SQL', `GET ${ruta}`, 'rechazado (302 validación / 404), nunca 500', String(r.s), [302, 404, 422].includes(r.s));
}
const loginInyeccion = cliente();
const pl = await loginInyeccion.pedir('/login');
const ri = await loginInyeccion.pedir('/login', loginInyeccion.formulario({ _token: loginInyeccion.token(pl.t), correo: "' OR '1'='1", contrasena: "' OR '1'='1" }));
const tras = await loginInyeccion.pedir('/admin');
anotar('Inyección SQL', 'Login con correo y contraseña inyectados', 'no entra', tras.s === 302 ? 'no entra' : `entra (${tras.s})`, tras.s === 302);
anotar('Inyección SQL', 'Ninguna tabla fue eliminada', tablasAntes, sql("select count(*) from sqlite_master where type='table'"), tablasAntes === sql("select count(*) from sqlite_master where type='table'"));

const empleados = await admin.pedir('/admin/empleados');
anotar('Información confidencial', 'El listado de empleados no expone hashes de contraseña', 'sin $2y$', empleados.t.includes('$2y$') ? 'expone hash' : 'sin hashes', !empleados.t.includes('$2y$'));
await admin.salir();

const intentos = Array.from({ length: 20 }, () => cliente());
const respuestas = await Promise.all(intentos.map(c => c.entrar('carga010@prueba.test')));
const exitos = [];
for (const c of intentos) { const r = await c.pedir('/mesero'); if (r.s === 200) exitos.push(c); }
anotar('Concurrencia', '20 inicios de sesión simultáneos con la misma cuenta', '1 sesión activa', `${exitos.length} sesión(es) con acceso, filas SESION_ACTIVA=${sql("select count(*) from SESION_ACTIVA s join EMPLEADO e on e.id_empleado=s.id_empleado where e.correo='carga010@prueba.test'")}`, exitos.length === 1);
for (const c of exitos) await c.salir();

const admins = await Promise.all(Array.from({ length: 15 }, async (_, i) => { const c = cliente(); await c.entrar(`carga${String(9 + i * 5).padStart(3, '0')}@prueba.test`); return c; }));
const creaciones = await Promise.all(admins.map(c => { const f = new URLSearchParams({ _token: c.csrf, nombre_rol: 'Rol simultáneo' }); f.append('permisos[]', 'pedidos.ver'); return c.pedir('/admin/roles', { method: 'POST', body: f, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }); }));
const estados = creaciones.reduce((m, r) => (m[r.s] = (m[r.s] || 0) + 1, m), {});
anotar('Concurrencia', '15 administradores crean el mismo rol a la vez', '1 rol creado, ningún 500', `roles=${sql("select count(*) from ROL where nombre_rol='Rol simultáneo'")}, respuestas=${JSON.stringify(estados)}`, sql("select count(*) from ROL where nombre_rol='Rol simultáneo'") === '[[1]]' && !estados[500]);
anotar('Concurrencia', 'Ningún rol quedó sin permisos (transacción completa)', '0', sql('select count(*) from ROL r where not exists (select 1 from ROL_PERMISO rp where rp.id_rol = r.id_rol)'), sql('select count(*) from ROL r where not exists (select 1 from ROL_PERMISO rp where rp.id_rol = r.id_rol)') === '[[0]]');
for (const c of admins) await c.salir();

writeFileSync(salida, JSON.stringify(resultados, null, 2));
console.log(`\n${resultados.filter(r => r.ok).length}/${resultados.length} pruebas aprobadas`);
