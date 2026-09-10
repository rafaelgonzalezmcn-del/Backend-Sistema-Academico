<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Académico API - Documentación</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; margin-bottom: 10px; }
        h2 { color: #555; margin-bottom: 15px; border-bottom: 2px solid #ddd; padding-bottom: 8px; }
        .description { color: #666; margin-bottom: 30px; }
        .section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .endpoint { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .endpoint:last-child { border-bottom: none; }
        .method { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; margin-right: 10px; }
        .method.get { background: #61affe; color: white; }
        .method.post { background: #49cc90; color: white; }
        .method.put { background: #fca130; color: white; }
        .method.delete { background: #f93e3e; color: white; }
        .method.patch { background: #50e3c2; color: white; }
        .path { font-family: monospace; color: #333; font-size: 14px; }
        .summary { color: #666; font-size: 13px; margin-top: 5px; }
        .auth-badge { display: inline-block; background: #ffd700; color: #333; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 10px; }
        .auth-public { background: #e8f5e9; color: #2e7d32; }
        .auth-require { background: #fff3e0; color: #e65100; }
        .auth-admin { background: #fce4ec; color: #c2185b; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; font-size: 12px; }
        pre { background: #2d2d2d; color: #ccc; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f4f4f4; }
        .tip { background: #e3f2fd; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📚 Sistema Académico API</h1>
        <p class="description">Documentación de la API REST del Sistema de Gestión Académica</p>
        
        <div class="section">
            <h2>🚀 Información General</h2>
            <p><strong>Base URL:</strong> <code>http://localhost:8000</code></p>
            <p><strong>Autenticación:</strong> Bearer JWT Token (Laravel Sanctum)</p>
            <div class="tip">
                <strong>Cómo usar:</strong><br>
                1. Hacer POST a <code>/api/login</code> con email y password<br>
                2. Obtendrás un token en la respuesta<br>
                3. Incluir en headers: <code>Authorization: Bearer {token}</code>
            </div>
        </div>

        <div class="section">
            <h2>🔐 Autenticación</h2>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/login</span>
                <span class="auth-badge auth-public">PÚBLICO</span>
                <p class="summary">Iniciar sesión - retorna token JWT</p>
                <pre>{
  "email": "admin@admin.com",
  "password": "admin123"
}</pre>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/logout</span>
                <span class="auth-badge auth-require">REQUIERE AUTH</span>
                <p class="summary">Cerrar sesión - invalida el token</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/me</span>
                <span class="auth-badge auth-require">REQUIERE AUTH</span>
                <p class="summary">Obtener datos del usuario autenticado</p>
            </div>
        </div>

        <div class="section">
            <h2>👥 Usuarios (Admin)</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/users</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Listar usuarios con paginación. Params: page, per_page, role, count_only</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/users</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Crear usuario</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/users/{user}</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Ver usuario específico</p>
            </div>
            <div class="endpoint">
                <span class="method put">PUT</span>
                <span class="path">/api/users/{user}</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Actualizar usuario</p>
            </div>
            <div class="endpoint">
                <span class="method delete">DELETE</span>
                <span class="path">/api/users/{user}</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Eliminar usuario</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/users/check-identification</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Validar Cédula en tiempo real</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/users/check-email</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Validar Email en tiempo real</p>
            </div>
        </div>

        <div class="section">
            <h2>📅 Años Lectivos</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/school-years</span>
                <span class="auth-badge auth-public">PÚBLICO</span>
                <p class="summary">Listar años lectivos</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/school-years/active</span>
                <span class="auth-badge auth-public">PÚBLICO</span>
                <p class="summary">Obtener año lectivo activo</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/school-years</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Crear año lectivo</p>
            </div>
            <div class="endpoint">
                <span class="method put">PUT</span>
                <span class="path">/api/school-years/{schoolYear}</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Actualizar año lectivo</p>
            </div>
            <div class="endpoint">
                <span class="method delete">DELETE</span>
                <span class="path">/api/school-years/{schoolYear}</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Eliminar año lectivo</p>
            </div>
            <div class="endpoint">
                <span class="method put">PUT</span>
                <span class="path">/api/school-years/{id}/activate</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Activar año lectivo</p>
            </div>
        </div>

        <div class="section">
            <h2>📊 Grados</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/grades</span>
                <span class="auth-badge auth-public">PÚBLICO</span>
                <p class="summary">Listar grados</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/grades</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Crear grado</p>
            </div>
            <div class="endpoint">
                <span class="method put">PUT</span>
                <span class="path">/api/grades/{grade}</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Actualizar grado</p>
            </div>
            <div class="endpoint">
                <span class="method delete">DELETE</span>
                <span class="path">/api/grades/{grade}</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Eliminar grado</p>
            </div>
        </div>

        <div class="section">
            <h2>🏫 Secciones</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/sections</span>
                <span class="auth-badge auth-public">PÚBLICO</span>
                <p class="summary">Listar secciones</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/sections/{id}/schedule</span>
                <span class="auth-badge auth-public">PÚBLICO</span>
                <p class="summary">Ver horario de sección</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/sections</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Crear sección</p>
            </div>
        </div>

        <div class="section">
            <h2>📚 Materias</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/subjects</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Listar materias</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/subjects/{subject}/participantes</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Listar participantes de una materia</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/subjects</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Crear materia</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/subjects/{subject}/sections/{section}/close</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Cerrar curso (profesor/admin)</p>
            </div>
        </div>

        <div class="section">
            <h2>🕐 Horarios</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/class-schedules</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Listar horarios</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/my-schedule</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Mi horario según rol</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/teachers/{id}/schedule</span>
                <span class="auth-badge auth-public">PÚBLICO</span>
                <p class="summary">Horario de un profesor (público)</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/students/{sectionId}/schedule</span>
                <span class="auth-badge auth-public">PÚBLICO</span>
                <p class="summary">Horario de estudiantes (público)</p>
            </div>
        </div>

        <div class="section">
            <h2>📦 Módulos y Materiales</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/materias/{materiaId}/modulos</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Listar módulos</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/modulos</span>
                <span class="auth-badge auth-admin">ADMIN/PROFESOR</span>
                <p class="summary">Crear módulo</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/modulos/{modulo}/materiales</span>
                <span class="auth-badge auth-admin">ADMIN/PROFESOR</span>
                <p class="summary">Subir material</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/materiales/{material}/descargar</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Descargar material</p>
            </div>
        </div>

        <div class="section">
            <h2>📝 Tareas</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/modulos/{moduloId}/tareas</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Listar tareas</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/tareas</span>
                <span class="auth-badge auth-admin">PROFESOR</span>
                <p class="summary">Crear tarea</p>
            </div>
            <div class="endpoint">
                <span class="method put">PUT</span>
                <span class="path">/api/tareas/{tarea}</span>
                <span class="auth-badge auth-admin">PROFESOR</span>
                <p class="summary">Actualizar tarea</p>
            </div>
            <div class="endpoint">
                <span class="method delete">DELETE</span>
                <span class="path">/api/tareas/{tarea}</span>
                <span class="auth-badge auth-admin">PROFESOR</span>
                <p class="summary">Eliminar tarea</p>
            </div>
        </div>

        <div class="section">
            <h2>📋 Entregas</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/entregas/{tarea}</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Listar entregas de una tarea</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/entregas/{tarea}</span>
                <span class="auth-badge auth-require">ESTUDIANTE</span>
                <p class="summary">Entregar tarea</p>
            </div>
            <div class="endpoint">
                <span class="method put">PUT</span>
                <span class="path">/api/entregas/calificar/{entrega}</span>
                <span class="auth-badge auth-admin">PROFESOR</span>
                <p class="summary">Calificar entrega</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/entregas/descargar/{entrega}</span>
                <span class="auth-badge auth-admin">PROFESOR/ADMIN</span>
                <p class="summary">Descargar archivo de entrega</p>
            </div>
        </div>

        <div class="section">
            <h2>📈 Parciales y Parámetros</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/modulos/{moduloId}/parciales</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Listar parciales</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/parciales</span>
                <span class="auth-badge auth-admin">PROFESOR</span>
                <p class="summary">Crear parcial</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/parciales/{parcialId}/parametros</span>
                <span class="auth-badge auth-require">AUTH</span>
                <p class="summary">Listar parámetros de un parcial</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/modulos/{moduloId}/notas/resumen</span>
                <span class="auth-badge auth-require">PROFESOR</span>
                <p class="summary">Resumen de notas</p>
            </div>
        </div>

        <div class="section">
            <h2>👨‍🏫 Dashboard Profesor</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/teacher/dashboard</span>
                <span class="auth-badge auth-require">PROFESOR</span>
                <p class="summary">Dashboard del profesor</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/teacher/courses/stats</span>
                <span class="auth-badge auth-require">PROFESOR</span>
                <p class="summary">Estadísticas de cursos</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/my-subjects</span>
                <span class="auth-badge auth-require">PROFESOR</span>
                <p class="summary">Mis materias como profesor</p>
            </div>
        </div>

        <div class="section">
            <h2>⚙️ Admin</h2>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/admin/dashboard/stats</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Estadísticas del dashboard</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/roles</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Listar todos los roles</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/teachers</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Listar profesores</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/activity-logs</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Logs de actividad</p>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/admin/enrollments</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Matricular estudiante</p>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/admin/students/{student}/academic-history</span>
                <span class="auth-badge auth-admin">ADMIN</span>
                <p class="summary">Historial académico</p>
            </div>
        </div>

        <div class="section">
            <h2>📋 Códigos de Respuesta</h2>
            <table>
                <tr><th>Código</th><th>Descripción</th></tr>
                <tr><td><code>200</code></td><td>Éxito</td></tr>
                <tr><td><code>201</code></td><td>Creado exitosamente</td></tr>
                <tr><td><code>400</code></td><td>Solicitud incorrecta</td></tr>
                <tr><td><code>401</code></td><td>No autenticado</td></tr>
                <tr><td><code>403</code></td><td>Sin permisos</td></tr>
                <tr><td><code>404</code></td><td>No encontrado</td></tr>
                <tr><td><code>409</code></td><td>Conflicto (registro duplicado)</td></tr>
                <tr><td><code>422</code></td><td>Error de validación</td></tr>
                <tr><td><code>429</code></td><td>Demasiadas solicitudes (rate limit)</td></tr>
                <tr><td><code>500</code></td><td>Error del servidor</td></tr>
            </table>
        </div>

        <div class="section">
            <h2>🧪 Probar con curl</h2>
            <pre># Login
curl -X POST http://localhost:8000/api/login ^
  -H "Content-Type: application/json" ^
  -d "{\"email\":\"admin@admin.com\",\"password\":\"admin123\"}"

# Listar usuarios (con token)
curl -X GET http://localhost:8000/api/users ^
  -H "Authorization: Bearer TU_TOKEN_AQUI"</pre>
        </div>
    </div>
</body>
</html>