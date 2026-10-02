@php
    // Formato de número para documentos: 2 decimales con coma (ej.: 8,50)
    $num = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de calificaciones — {{ $acta['materia'] }}</title>
    <style>
        @page { margin: 28mm 14mm 22mm 14mm; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5pt;
            color: #1f2937;
        }

        /* Encabezado y pie fijos en cada página */
        header {
            position: fixed;
            top: -20mm;
            left: 0;
            right: 0;
            height: 16mm;
            border-bottom: 1.5pt solid #1e3a5f;
        }
        header .institucion { font-size: 12pt; font-weight: bold; color: #1e3a5f; }
        header .sistema { font-size: 8pt; color: #6b7280; }
        header .emitido { position: absolute; right: 0; top: 2mm; font-size: 8pt; color: #6b7280; text-align: right; }

        footer {
            position: fixed;
            bottom: -14mm;
            left: 0;
            right: 0;
            height: 8mm;
            font-size: 7.5pt;
            color: #6b7280;
            border-top: 0.5pt solid #d1d5db;
            padding-top: 2mm;
        }

        h1 {
            text-align: center;
            font-size: 14pt;
            letter-spacing: 1pt;
            margin: 0 0 1mm 0;
            color: #111827;
        }
        .subtitulo { text-align: center; font-size: 9pt; color: #4b5563; margin-bottom: 5mm; }

        .provisional {
            border: 1pt solid #b45309;
            background: #fef3c7;
            color: #92400e;
            text-align: center;
            font-weight: bold;
            padding: 2mm;
            margin-bottom: 4mm;
            font-size: 9pt;
        }

        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
        table.datos td { padding: 1.2mm 2mm; font-size: 9pt; }
        table.datos td.etiqueta { color: #6b7280; width: 18%; }
        table.datos td.valor { font-weight: bold; width: 32%; }

        table.notas { width: 100%; border-collapse: collapse; }
        table.notas th {
            background: #1e3a5f;
            color: #ffffff;
            font-size: 8.5pt;
            padding: 2mm 1.5mm;
            border: 0.5pt solid #1e3a5f;
        }
        table.notas th small { font-weight: normal; color: #cbd5e1; }
        table.notas td {
            border: 0.5pt solid #d1d5db;
            padding: 1.6mm 1.5mm;
            font-size: 9pt;
        }
        table.notas tr:nth-child(even) td { background: #f3f4f6; }
        .centro { text-align: center; }
        .derecha { text-align: right; }
        .total { font-weight: bold; }

        .estado-aprobado { color: #166534; font-weight: bold; }
        .estado-reprobado { color: #b91c1c; font-weight: bold; }
        .estado-otro { color: #6b7280; }

        table.resumen { margin-top: 5mm; border-collapse: collapse; }
        table.resumen td { padding: 1mm 4mm 1mm 0; font-size: 9pt; }
        table.resumen td.valor { font-weight: bold; }

        .nota-pie { margin-top: 3mm; font-size: 7.5pt; color: #6b7280; }

        table.firmas { width: 100%; margin-top: 22mm; }
        table.firmas td { width: 50%; text-align: center; font-size: 9pt; }
        .linea-firma { border-top: 0.75pt solid #374151; width: 65%; margin: 0 auto 1.5mm auto; }
        .cargo { color: #6b7280; font-size: 8pt; }

        .sin-estudiantes { text-align: center; color: #6b7280; padding: 8mm; }
    </style>
</head>
<body>
    <header>
        <div class="institucion">{{ $acta['institucion'] }}</div>
        <div class="sistema">{{ $acta['sistema'] }}</div>
        <div class="emitido">Emitido: {{ $acta['emitido'] }}</div>
    </header>

    <footer>
        Acta de calificaciones · {{ $acta['materia'] }} · {{ $acta['grado'] }} "{{ $acta['seccion'] }}" · {{ $acta['anio_lectivo'] }}
    </footer>

    <h1>ACTA DE CALIFICACIONES</h1>
    <div class="subtitulo">
        {{ $acta['cerrado'] ? 'Calificaciones finales del curso' : 'Calificaciones a la fecha de emisión' }}
    </div>

    @unless($acta['cerrado'])
        <div class="provisional">
            DOCUMENTO PROVISIONAL — el curso aún no ha sido cerrado y las notas pueden cambiar
        </div>
    @endunless

    <table class="datos">
        <tr>
            <td class="etiqueta">Asignatura:</td>
            <td class="valor">{{ $acta['materia'] }}</td>
            <td class="etiqueta">Año lectivo:</td>
            <td class="valor">{{ $acta['anio_lectivo'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Grado / Paralelo:</td>
            <td class="valor">{{ $acta['grado'] }} "{{ $acta['seccion'] }}"</td>
            <td class="etiqueta">Docente:</td>
            <td class="valor">{{ $acta['profesor'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Estado del curso:</td>
            <td class="valor">{{ $acta['cerrado'] ? 'Cerrado el ' . $acta['fecha_cierre'] : 'En curso' }}</td>
            <td class="etiqueta">Nota mínima:</td>
            <td class="valor">{{ $num($acta['porcentaje_aprobacion']) }} %</td>
        </tr>
    </table>

    <table class="notas">
        <thead>
            <tr>
                <th style="width: 5%">N.º</th>
                <th>Estudiante</th>
                <th style="width: 12%">Cédula</th>
                @foreach($acta['parciales'] as $parcial)
                    <th style="width: 9%">{{ $parcial['nombre'] }}<br><small>/ {{ $num($parcial['nota_maxima']) }}</small></th>
                @endforeach
                <th style="width: 9%">Total<br><small>/ {{ $num($acta['nota_maxima_total']) }}</small></th>
                <th style="width: 8%">%</th>
                <th style="width: 13%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($acta['filas'] as $fila)
                <tr>
                    <td class="centro">{{ $fila['numero'] }}</td>
                    <td>{{ $fila['estudiante'] }}</td>
                    <td class="centro">{{ $fila['identificacion'] ?? '—' }}</td>
                    @foreach($fila['notas_parciales'] as $nota)
                        <td class="derecha">{{ $num($nota) }}</td>
                    @endforeach
                    <td class="derecha total">{{ $num($fila['total']) }}</td>
                    <td class="derecha">{{ $num($fila['porcentaje']) }}</td>
                    <td class="centro estado-{{ in_array($fila['estado'], ['aprobado', 'reprobado']) ? $fila['estado'] : 'otro' }}">
                        {{ $fila['estado_texto'] }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 6 + count($acta['parciales']) }}" class="sin-estudiantes">
                        No hay estudiantes en este curso.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="resumen">
        <tr>
            <td>Estudiantes:</td><td class="valor">{{ $acta['resumen']['total'] }}</td>
            <td>Aprobados:</td><td class="valor">{{ $acta['resumen']['aprobados'] }}</td>
            <td>Reprobados:</td><td class="valor">{{ $acta['resumen']['reprobados'] }}</td>
            <td>Sin calificaciones:</td><td class="valor">{{ $acta['resumen']['sin_calificaciones'] }}</td>
            <td>Promedio del curso:</td><td class="valor">{{ $num($acta['resumen']['promedio_porcentaje']) }} %</td>
        </tr>
    </table>

    <div class="nota-pie">
        La nota de cada parcial se calcula con los porcentajes de sus parámetros (actividades, tareas,
        exámenes, etc.). Total = suma de los parciales. Se aprueba con el
        {{ $num($acta['porcentaje_aprobacion']) }} % de la nota máxima.
    </div>

    <table class="firmas">
        <tr>
            <td>
                <div class="linea-firma"></div>
                {{ $acta['profesor'] ?? 'Docente' }}<br>
                <span class="cargo">Docente de la asignatura</span>
            </td>
            <td>
                <div class="linea-firma"></div>
                &nbsp;<br>
                <span class="cargo">Secretaría / Rectorado</span>
            </td>
        </tr>
    </table>
</body>
</html>
