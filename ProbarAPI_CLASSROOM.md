## Probar Google Classroom en desarrollo

La integración lee los cursos y las actividades de la cuenta de Google.

### 1. Preparar Google Cloud

Quien administre el proyecto de Google Cloud (Luis Reyes) debe:

1. Tener habilitada **Google Classroom API** y configurar la pantalla de
   consentimiento OAuth en modo de pruebas. (listo)
2. Añadir el correo Google de cada compañero en **Usuarios de prueba**. (Deben pasarme sus correos)
3. Crear o compartir de forma segura un cliente OAuth de tipo **Aplicación web**. (Se compartirán en WhatsApp)
   No envíen el `client_secret` por el repositorio ni lo agreguen a ningún commit.

### 2. Descargar la rama e instalar dependencias

Desde una terminal en la carpeta donde quieran guardar el proyecto:

```bash
git fetch origin
git switch --track origin/integracion/classroom-api
composer install
npm install
```

Si ya tienen creada la rama local, en vez de `git switch --track ...` ejecuten:

```bash
git switch integracion/classroom-api
git pull
```

La rama `integracion/classroom-api` debe estar publicada en `origin` para que
los demás puedan descargarla. Si `git fetch` no encuentra
`origin/integracion/classroom-api`, quien tenga la rama local debe publicarla
primero.

### 3. Configurar el entorno local

Copien `.env.example` a `.env` si todavía no existe. En PowerShell:

```powershell
Copy-Item .env.example .env
```

En macOS/Linux:

```bash
cp .env.example .env
```

Configuren las variables locales de la aplicación y la base de datos. Como
mínimo:

```dotenv
APP_URL=http://localhost:8000
GOOGLE_CLASSROOM_CLIENT_ID=valor-compartido-por-luis_reyes
GOOGLE_CLASSROOM_CLIENT_SECRET=secreto-compartido-de-forma-segura-por-luis_reyes
GOOGLE_CLASSROOM_REDIRECT_URI="${APP_URL}/classroom/callback"
```

Cada equipo local debe tener las credenciales del cliente OAuth y la misma URI
registrada en Google Cloud. No copien el `.env` de otra persona. Si cambian
`APP_URL` o el puerto, también deben registrar esa URI exacta en Google Cloud y
actualizar `GOOGLE_CLASSROOM_REDIRECT_URI`.

### 4. Preparar la base de datos y compilar recursos

El esquema base del proyecto se encuentra en `database/study_track.sql`; no todas
las tablas de StudyTrack se crean con migraciones de Laravel. Creen una base de
datos local (diferente a la que tienen en el proyecto) e importen ese archivo en ella.
El archivo es un volcado de base de datos: impórtenlo únicamente en una base de
datos de desarrollo desechable, nunca sobre una base con información que quieran
conservar.

```bash
php artisan migrate
npm run build
```

Las migraciones agregan o actualizan las tablas y columnas necesarias para
Classroom. No hace falta ejecutar `db:seed` para probar OAuth o sincronización.
Si Laravel conserva una configuración anterior, ejecuten
`php artisan config:clear`.

### 5. Iniciar la aplicación y conectar Classroom

En una terminal:

```bash
php artisan serve --host=localhost --port=8000
```

Abre `http://localhost:8000`, crea una cuenta local o inicia sesión y, desde el
panel, elige **Conectar Google Classroom**. Autoriza el acceso con el correo
añadido a Usuarios de prueba que tenga acceso a los cursos deseados. Luego usa
**Sincronizar**. Los cursos nuevos aparecen como pendientes hasta que se asignen
a un semestre.

La sincronización manual funciona sin iniciar un proceso adicional. Para probar
también la sincronización automática cada 15 minutos, ejecuten en otra terminal:

```bash
php artisan schedule:work
```

### Problemas frecuentes

- **`redirect_uri_mismatch`:** comprueben que `APP_URL`, `GOOGLE_CLASSROOM_REDIRECT_URI`
  y la URI registrada en Google Cloud coincidan exactamente, incluido el puerto
  y el protocolo (`http`/`https`).
- **`access_denied` o acceso bloqueado:** verifiquen que se autorizó con una
  cuenta incluida en Usuarios de prueba y que el consentimiento OAuth sigue en
  modo de pruebas.
- **No aparecen algunos cursos:** comprueben que la cuenta autorizada esté
  inscrita como estudiante. Ser usuario de prueba OAuth no inscribe una cuenta
  en cursos.
- **Error de conexión a la base de datos:** revisen `DB_CONNECTION`, host, puerto,
  nombre de la base, usuario y contraseña en el `.env`; después vuelvan a
  ejecutar `php artisan migrate`.
