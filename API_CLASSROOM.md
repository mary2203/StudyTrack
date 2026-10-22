# Integración con Google Classroom

StudyTrack importa cursos, tareas, estados de entrega y calificaciones de la cuenta Google que autoriza cada usuario. La integración solo lee Classroom: no crea, edita ni elimina cursos o tareas en Google.

## Configuración de Google Cloud

1. Crea o selecciona un proyecto en [Google Cloud Console](https://console.cloud.google.com/).
2. Habilita **Google Classroom API**.
3. Configura la pantalla de consentimiento OAuth y agrega las cuentas de prueba si la aplicación sigue en modo de pruebas.
4. Crea un cliente OAuth de tipo **Aplicación web**. Registra como URI de redireccionamiento autorizada la URL completa `APP_URL/classroom/callback` (por ejemplo, `http://localhost:8000/classroom/callback` en desarrollo).
5. Copia las credenciales al `.env` local; no las publiques ni las agregues al repositorio:

   ```dotenv
   GOOGLE_CLASSROOM_CLIENT_ID=...
   GOOGLE_CLASSROOM_CLIENT_SECRET=...
   GOOGLE_CLASSROOM_REDIRECT_URI="${APP_URL}/classroom/callback"
   ```

6. Ejecuta `php artisan migrate` para crear la conexión OAuth y los identificadores de Classroom. El refresh token se guarda cifrado usando `APP_KEY`.
7. Inicia el scheduler en desarrollo con `php artisan schedule:work`. En producción, configura el scheduler de Laravel para ejecutar `php artisan schedule:run` cada minuto. La sincronización de Classroom se programa cada 15 minutos.

La autorización solicita únicamente lectura de cursos, tareas y entregas/calificaciones del usuario (`classroom.courses.readonly`, `classroom.coursework.me.readonly` y `classroom.student-submissions.me.readonly`), además de su correo de Google. Cada usuario conecta su propia cuenta desde el panel.

## Comportamiento

- Todos los cursos nuevos quedan pendientes de asignar porque el estado de Classroom no indica a qué semestre académico pertenecen. El usuario elige un semestre existente, crea uno histórico o los omite. Omitirlos solo los oculta de la lista pendiente; no elimina datos de Classroom.
- Las tareas publicadas se importan como actividades. StudyTrack conserva fechas ausentes como «Sin fecha», y muestra el estado reportado por Classroom y la calificación asignada por el docente.
- La sincronización manual está disponible en el panel además del proceso automático.
- Los cursos y actividades sincronizados son de solo lectura en StudyTrack, incluidos el estado, la calificación y la eliminación. El usuario sí puede crear actividades locales dentro de un curso sincronizado; esas actividades no se escriben en Classroom.
- Desconectar la cuenta elimina la credencial guardada, pero conserva los cursos y actividades ya importados.

## Notas para desarrollo

- No se necesita instalar un cliente de Google adicional: la integración usa el cliente HTTP de Laravel.
- La API debe estar habilitada y la cuenta autorizada debe tener acceso de estudiante a los cursos.
- Para que el primer inicio de sesión OAuth funcione, `APP_URL`, la URI autorizada de Google y `GOOGLE_CLASSROOM_REDIRECT_URI` deben coincidir exactamente.
- Google puede requerir verificación OAuth antes de permitir el uso público de los permisos de Classroom.
