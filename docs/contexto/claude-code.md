# Claude Code en el backend

Qué viene con el repo, qué instala cada desarrollador y qué es personal. El frontend tiene su
propia versión de esta guía.

## Compartido (en Git, llega con `git pull`)

| Ruta | Qué es |
|---|---|
| `AGENTS.md` (vía `CLAUDE.md`) | reglas generales; se carga en cada sesión |
| bloque `<laravel-boost-guidelines>` de `CLAUDE.md` | lo regenera `php artisan boost:update` según `boost.json`; no editarlo a mano |
| `.mcp.json` | servidor MCP `laravel-boost` (`php artisan boost:mcp`) |
| `docs/contexto/` | detalle por tema; se lee solo al tocar ese tema |
| `.claude/skills/` de Boost | `laravel-best-practices`, `testing-best-practices`, `socialite-development`, `tailwindcss-development`, `deploying-to-cloud`, `infer-conventions` |
| `.claude/skills/sql-optimization` | medir e indexar en MariaDB |
| `.claude/skills/revision-codigo` | lista de trampas para revisar un cambio |
| `.claude/agents/` | agentes de solo lectura (abajo) |

## Agentes

| Agente | Para qué | Modelo | Turnos |
|---|---|---|---|
| `log-analyzer` | errores en `storage/logs/laravel.log` y archivos grandes | haiku | 12 |
| `laravel-reviewer` | revisar un cambio mediano o grande | sonnet | 15 |
| `database-analyst` | consultas lentas, EXPLAIN, índices (solo `SELECT`/`SHOW`/`EXPLAIN`/`ANALYZE SELECT`) | sonnet | 20 |
| `security-auditor` | auth, permisos, IDOR, inyección, gate del frontend vs. API | sonnet | 25 |

Ninguno tiene `Edit`/`Write`. Corren en su propio contexto y devuelven un informe corto
(hallazgos, archivos, impacto, recomendación). Un cambio chico no se delega.

## Lo que cada desarrollador instala

| Herramienta | Obligatoria | Cómo |
|---|---|---|
| Claude Code | sí | <https://docs.claude.com/claude-code> |
| PHP + Composer | sí | el MCP de Boost corre con `php artisan boost:mcp` |
| Python 3 | solo si usas Headroom | en Windows se invoca con `py` (el alias `python3` de la tienda no funciona) |
| Plugin Ponytail | opcional | `/plugin` → marketplace `DietrichGebert/ponytail` → `ponytail` |
| Plugin Headroom | opcional | `/plugin` → "Headroom: Large File and Log Reader" (útil aquí por `laravel.log`) |

Las reglas de `AGENTS.md` funcionan sin los plugins; `log-analyzer` usa Headroom si está y si
no, `grep`/`tail`. Ningún archivo compartido los exige.

## Recomendado: desactivar Vercel en este repo

Si tienes el plugin de Vercel, desactívalo aquí: OMNIA no usa Vercel y el plugin carga unas 45
descripciones de skills y un texto largo en cada sesión. En tu `.claude/settings.local.json`
(personal; no se commitea) agrega:

```json
"enabledPlugins": { "vercel@claude-plugins-official": false }
```

Solo aplica a este repo (sigue activo en tus otros proyectos) y a las sesiones nuevas.

## Personal (nunca en Git)

- `.claude/settings.local.json`: permisos aprobados y plugins activados solo para ti (está en
  `.gitignore`).
- `~/.claude/`: plugins de usuario, memoria automática y ajustes globales.

## Mind Palace

No está instalado ni existe en los marketplaces configurados. Para conservar contexto entre
sesiones se usa lo que ya hay: `docs/contexto/` (compartido) y la memoria automática de
Claude Code (personal).
