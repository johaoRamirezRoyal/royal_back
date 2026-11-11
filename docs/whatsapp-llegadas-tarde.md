# WhatsApp para llegadas tarde — checklist de configuración

Notificación por WhatsApp a los acudientes cuando se registra una llegada tarde,
paralela a la carta que ya se envía por correo (`LlegadasTarde::enviarNotificacionLlegadaTarde()`
en `app/Services/LlegadasTardeEstudiantes/LlegadasTarde.php`). El código ya está
implementado (`app/Services/WhatsAppService.php`); lo que falta es la parte que solo
se puede hacer desde la cuenta de Meta del colegio.

Mientras no se complete este checklist, deja `WHATSAPP_ENABLED=false` en `.env` — con
eso el sistema sigue funcionando normal (correo + carta), solo que no intenta llamar
a la API de WhatsApp; queda constancia en el log de cada intento.

## 1. Crear la cuenta de WhatsApp Business (Meta)

1. Entra a [Meta for Developers](https://developers.facebook.com/) con una cuenta de
   Facebook que administre (o vaya a administrar) el colegio.
2. Crea una **App de tipo "Business"** en el panel de Meta for Developers.
3. Dentro de la app, agrega el producto **WhatsApp**. Meta crea automáticamente una
   **WhatsApp Business Account (WABA)** de prueba con un número de prueba gratuito —
   sirve para probar el flujo completo antes de usar el número real del colegio.
4. Para producción, agrega el **número de WhatsApp real del colegio** a la WABA
   (Configuración de WhatsApp → Números de teléfono → Agregar número) y verifícalo
   (Meta envía un código por SMS/llamada). Un número que ya use WhatsApp normal (app
   móvil) debe darse de baja de ahí primero — no puede estar activo en los dos lados
   a la vez.
5. Anota el **Phone Number ID** que Meta le asigna a ese número (no es el número de
   teléfono en sí, es un ID interno de Meta) — va en `WHATSAPP_PHONE_NUMBER_ID`.

## 2. Generar el token de acceso

1. En la misma app, ve a WhatsApp → Configuración de la API.
2. Para pruebas rápidas puedes usar el **token temporal** que Meta genera ahí mismo
   (dura 24h) — útil para probar el checklist completo antes de comprometerte al
   siguiente paso.
3. Para producción, crea un **System User** en el Meta Business Manager
   (Configuración del negocio → Usuarios → Usuarios del sistema), asígnale el activo
   WhatsApp (la app/WABA) con permiso `whatsapp_business_messaging`, y genera un
   **token permanente** desde ahí. Ese es el que va en `WHATSAPP_ACCESS_TOKEN`
   (guárdalo como secreto, igual que cualquier otra credencial en `.env`).

## 3. Crear y aprobar las tres plantillas

WhatsApp Business API **exige plantillas pre-aprobadas** para cualquier mensaje que
inicia el negocio (no es texto libre como el correo) — Meta las revisa manualmente,
normalmente en minutos a un día. Categoría recomendada: **Utility** (transaccional/
informativo, no promocional), que tiene mejor tasa y velocidad de aprobación que
Marketing.

Crea las tres desde Meta Business Manager → WhatsApp Manager → Plantillas de mensaje.
Los nombres por defecto que ya usa el código son los siguientes (puedes usar otros,
solo actualiza `WHATSAPP_TEMPLATE_NORMAL`/`_ADVERTENCIA`/`_LIMITE` en `.env` para que
coincidan):

### `llegada_tarde_normal` (categoría Utility, idioma Español)

Body, 4 variables en este orden exacto:

```
Colegio Real Royal School informa que el estudiante {{1}}, del grado {{2}}, registró una llegada tarde el {{3}}. Lleva {{4}} llegada(s) tarde en el periodo académico actual.
```

### `llegada_tarde_advertencia` (categoría Utility, idioma Español)

Body, 3 variables:

```
Aviso de Vicerrectoría - Colegio Real Royal School: el estudiante {{1}}, del grado {{2}}, alcanzó {{3}} llegadas tarde en el periodo académico, el límite permitido. Una llegada tarde adicional impedirá su ingreso a la jornada escolar.
```

### `llegada_tarde_limite` (categoría Utility, idioma Español)

Body, 4 variables:

```
Notificación de incumplimiento - Colegio Real Royal School: el estudiante {{1}}, del grado {{2}}, superó el límite de {{3}} llegadas tarde por trimestre. En consecuencia, no podrá ingresar a la jornada escolar el {{4}}. Revise el correo enviado para más detalles.
```

Meta pide ejemplos de valores reales para cada variable al someter la plantilla (ej.
`{{1}}` → "Juan Pérez", `{{2}}` → "5°A", etc.) — cualquier valor plausible sirve, es
solo para que el revisor entienda el uso.

**Importante**: si cambias el texto o el número/orden de variables respecto a lo de
arriba, tienes que actualizar `LlegadasTarde::parametrosWhatsApp()` en
`app/Services/LlegadasTardeEstudiantes/LlegadasTarde.php` para que coincida — la API
rechaza el envío si la cantidad de parámetros no coincide con la plantilla aprobada.

## 4. Completar el `.env`

```
WHATSAPP_ENABLED=true
WHATSAPP_API_VERSION=v21.0
WHATSAPP_PHONE_NUMBER_ID=<el Phone Number ID del paso 1>
WHATSAPP_ACCESS_TOKEN=<el token permanente del paso 2>
WHATSAPP_TEMPLATE_LANG=es
WHATSAPP_TEMPLATE_NORMAL=llegada_tarde_normal
WHATSAPP_TEMPLATE_ADVERTENCIA=llegada_tarde_advertencia
WHATSAPP_TEMPLATE_LIMITE=llegada_tarde_limite
```

## 5. Probar

Registra una llegada tarde de prueba (para un estudiante con un acudiente que tenga
`celular` cargado en `estudiantes_padres`) y revisa `storage/logs/laravel.log` —
`WhatsAppService` deja constancia de cada intento, exitoso o no, con la respuesta
completa de la API en caso de error (motivo típico de rechazo: nombre de plantilla no
coincide, número de teléfono sin WhatsApp, o cantidad de variables distinta a la
aprobada).

## Notas de diseño

- El envío es **síncrono** (no usa colas), igual que el correo — un fallo de WhatsApp
  nunca bloquea el registro de la llegada tarde, `WhatsAppService` atrapa sus propios
  errores.
- Solo se notifica a los **acudientes activos** (`estudiantes_padres.celular` con
  `activo = 1`), no al estudiante — un menor normalmente no tiene número propio
  registrado para esto. Si se necesita cambiar, es un ajuste en
  `LlegadasTarde::enviarNotificacionLlegadaTarde()`.
- Los números de 10 dígitos (celular colombiano local) se normalizan anteponiendo el
  indicativo `57`; un número que ya traiga indicativo se deja tal cual
  (`WhatsAppService::normalizarNumero()`).
- El mismo criterio de tres niveles que ya existe para el correo (normal / advertencia
  en la llegada `cantidad_limite` / incumplimiento desde `cantidad_limite + 1` en
  adelante, repitiéndose en cada reincidencia) aplica igual para WhatsApp — ver
  `LlegadasTarde::plantillaWhatsApp()`.
