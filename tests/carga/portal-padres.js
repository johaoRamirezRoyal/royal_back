// Prueba de estrés del portal para padres y de «Fotos del curso» (k6). Solo lecturas: no publica
// ni borra nada. Cómo correrla y leer los resultados: tests/carga/README.md.
import http from "k6/http";
import { check, group, sleep } from "k6";

const BASE = __ENV.BASE_URL || "http://localhost:8000";
// Perfil de carga: "estres" (por defecto, sube hasta romper) o "humo" (una pasada corta para probar el script).
const PERFIL = __ENV.PERFIL || "estres";

const requerido = (nombre) => {
    if (!__ENV[nombre]) throw new Error(`Falta la variable ${nombre} (ver tests/carga/README.md)`);
    return __ENV[nombre];
};

const ETAPAS = {
    humo: { padres: [{ duration: "20s", target: 2 }], docentes: [{ duration: "20s", target: 1 }] },
    // Sube por escalones para ver dónde empieza a degradarse: 5 → 20 → 50 → 100 padres a la vez.
    estres: {
        padres: [
            { duration: "30s", target: 5 },
            { duration: "1m", target: 20 },
            { duration: "1m", target: 50 },
            { duration: "1m", target: 100 },
            { duration: "30s", target: 0 },
        ],
        docentes: [
            { duration: "30s", target: 2 },
            { duration: "2m", target: 10 },
            { duration: "1m", target: 20 },
            { duration: "30s", target: 0 },
        ],
    },
};

export const options = {
    scenarios: {
        padres: { executor: "ramping-vus", exec: "padre", startVUs: 0, stages: ETAPAS[PERFIL].padres, gracefulRampDown: "10s" },
        docentes: { executor: "ramping-vus", exec: "docente", startVUs: 0, stages: ETAPAS[PERFIL].docentes, gracefulRampDown: "10s" },
    },
    // Umbrales: la prueba "falla" si se pasan, pero corre completa para ver hasta dónde llega.
    thresholds: {
        http_req_failed: ["rate<0.01"],
        "http_req_duration{name:hijos}": ["p(95)<800"],
        "http_req_duration{name:semana}": ["p(95)<1000"],
        "http_req_duration{name:fotos_mes}": ["p(95)<1000"],
        "http_req_duration{name:fotos_calendario}": ["p(95)<800"],
        "http_req_duration{name:fotos_buscar}": ["p(95)<1000"],
        "http_req_duration{name:docente_listado}": ["p(95)<1000"],
    },
};

const login = (correo, pass, colegio) => {
    const res = http.post(
        `${BASE}/api/auth/login`,
        JSON.stringify({ user: correo, pass, ...(colegio ? { colegio } : {}) }),
        { headers: { "Content-Type": "application/json", Accept: "application/json" }, tags: { name: "login" } },
    );
    const token = res.cookies.token && res.cookies.token[0] ? res.cookies.token[0].value : null;
    if (res.status !== 200 || !token) throw new Error(`No se pudo iniciar sesión como ${correo}: ${res.status} ${res.body}`);
    return token;
};

const con = (token, name) => ({ headers: { Accept: "application/json", Cookie: `token=${token}` }, tags: { name } });

// Una sola sesión por perfil (como un padre con el portal abierto); el login no es lo que se mide.
export function setup() {
    const padre = login(requerido("PADRE_CORREO"), requerido("PADRE_PASS"), __ENV.COLEGIO || "royalschool");
    const docente = login(requerido("DOCENTE_CORREO"), requerido("DOCENTE_PASS"));

    const hijos = http.get(`${BASE}/api/portal-padres/hijos`, con(padre, "hijos")).json("data");
    if (!hijos || !hijos.length) throw new Error("El acudiente de prueba no tiene hijos vinculados");

    const cursos = http.get(`${BASE}/api/fotos-curso/cursos`, con(docente, "docente_cursos")).json("data");
    if (!cursos || !cursos.length) throw new Error("El docente de prueba no tiene cursos");

    const hoy = new Date();
    const mes = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, "0")}`;
    return { padre, docente, idHijo: hijos[0].id_user, idCurso: cursos[0].id, mes };
}

const ok = (res, nombre) => check(res, { [`${nombre}: 200`]: (r) => r.status === 200 });

const BUSQUEDAS = ["feria", "sociales", "octubre", "bandera", "regiones"];

/** Un padre abre el portal: Inicio (hijos, semana, calendario, fotos del mes) y a veces busca fotos. */
export function padre(d) {
    group("Inicio", () => {
        ok(http.get(`${BASE}/api/portal-padres/hijos`, con(d.padre, "hijos")), "hijos");
        const r = http.batch([
            ["GET", `${BASE}/api/portal-padres/hijos/${d.idHijo}/semana`, null, con(d.padre, "semana")],
            ["GET", `${BASE}/api/portal-padres/hijos/${d.idHijo}/fotos/calendario`, null, con(d.padre, "fotos_calendario")],
            ["GET", `${BASE}/api/portal-padres/hijos/${d.idHijo}/fotos?mes=${d.mes}`, null, con(d.padre, "fotos_mes")],
        ]);
        ok(r[0], "semana");
        ok(r[1], "fotos_calendario");
        ok(r[2], "fotos_mes");
    });
    sleep(1 + Math.random() * 2);
    if (Math.random() < 0.3) {
        group("Fotos: buscar", () => {
            const q = BUSQUEDAS[Math.floor(Math.random() * BUSQUEDAS.length)];
            ok(http.get(`${BASE}/api/portal-padres/hijos/${d.idHijo}/fotos?q=${q}`, con(d.padre, "fotos_buscar")), "fotos_buscar");
        });
        sleep(1 + Math.random() * 2);
    }
}

/** Un docente abre «Fotos del curso»: sus cursos, sus asignaturas y la lista del curso. */
export function docente(d) {
    group("Fotos del curso", () => {
        ok(http.get(`${BASE}/api/fotos-curso/cursos`, con(d.docente, "docente_cursos")), "docente_cursos");
        ok(http.get(`${BASE}/api/fotos-curso?id_curso=${d.idCurso}`, con(d.docente, "docente_listado")), "docente_listado");
        ok(http.get(`${BASE}/api/fotos-curso/opciones`, con(d.docente, "docente_opciones")), "docente_opciones");
    });
    sleep(2 + Math.random() * 3);
}
