# Implementación de API de Classroom

### Objetivo de la implementación
Sincronizar los cursos reales que se recibe en la Universidad por medio de su API, para no crearlos manualmente y tener la información exacta en tiempo real y de manera automática.

### Tareas para lograrlo
- [ ] Conectar la API a través de google cloud
- [ ] Obtener los cursos actuales
> [!WARNING]
> Al obtener los cursos, algunos puede que sean de semestres anteriores.
> (Para ese caso, realizar acciones: a=Eliminarlo de StudyTrack(no en classroom), b=Crear un semestre aparte).
- [ ] Obtener las tareas asignadas por el docente (Fecha, hora de entrega, y estado=[Entregado, No entregado, Marcado como completado])
> [!IMPORTANT]
> Esas tareas sincronizadas, en el sistema StudyTrack se interpretan como actividades.
- [ ] Obtener las calificaciones de cada tarea de los cursos.

> [!CAUTION]
> Tomar en cuenta de las acciones que no puede hacer el usuario, (CRUD hacia cursos sincronizados desde classroom), No puede editar calificaciones por ejemplo, UNICAMENTE puede crear alguna actividad dentro de ese curso que el docente se le olvido crear.