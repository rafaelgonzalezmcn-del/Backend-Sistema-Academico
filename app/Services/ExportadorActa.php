<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Convierte los datos del acta (ActaService::generar) en PDF o Excel.
 *
 * - PDF:   plantilla Blade resources/views/actas/calificaciones.blade.php + dompdf
 * - Excel: OpenSpout (.xlsx), con las notas como números para poder calcular
 */
class ExportadorActa
{
    private const AZUL = '1E3A5F';

    public function pdf(array $acta): string
    {
        $opciones = new DompdfOptions();
        $opciones->set('defaultFont', 'DejaVu Sans'); // soporta tildes y ñ
        $opciones->set('isRemoteEnabled', false);      // no descargar recursos externos

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml(view('actas.calificaciones', ['acta' => $acta])->render(), 'UTF-8');
        // Horizontal si hay muchas columnas de parciales
        $dompdf->setPaper('A4', count($acta['parciales']) > 3 ? 'landscape' : 'portrait');
        $dompdf->render();

        // Número de página en el pie
        $canvas = $dompdf->getCanvas();
        $fuente = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(
            $canvas->get_width() - 110,
            $canvas->get_height() - 34,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $fuente,
            7.5,
            [0.42, 0.45, 0.5]
        );

        return $dompdf->output();
    }

    /**
     * Genera el .xlsx en un archivo temporal y devuelve su ruta.
     */
    public function excel(array $acta): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'acta_') . '.xlsx';
        $columnas = 6 + count($acta['parciales']); // N.º, Estudiante, Cédula, parciales..., Total, %, Estado

        $opciones = new XlsxOptions();
        $opciones->setColumnWidth(6, 1);
        $opciones->setColumnWidth(38, 2);
        $opciones->setColumnWidth(14, 3);
        $opciones->setColumnWidthForRange(12, 4, $columnas - 1); // parciales, Total y % (sin superponerse con Estado)
        $opciones->setColumnWidth(19, $columnas); // Estado ("Sin calificaciones")
        // Título e institución ocupan todo el ancho
        $opciones->mergeCells(0, 1, $columnas - 1, 1);
        $opciones->mergeCells(0, 2, $columnas - 1, 2);
        // Impresión: A4 horizontal, todas las columnas en el ancho de una hoja
        $opciones->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, fitToHeight: 0, fitToWidth: 1));

        $writer = new XlsxWriter($opciones);
        $writer->openToFile($ruta);
        $hoja = $writer->getCurrentSheet();
        $hoja->setName('Acta');

        $titulo = (new Style())->setFontBold()->setFontSize(14)->setFontColor(self::AZUL);
        $negrita = (new Style())->setFontBold();
        $gris = (new Style())->setFontColor(Color::rgb(107, 114, 128));
        $etiqueta = (new Style())->setFontColor(Color::rgb(107, 114, 128))->setCellAlignment(CellAlignment::RIGHT);
        $aviso = (new Style())->setFontBold()->setFontColor(Color::rgb(146, 64, 14))->setBackgroundColor(Color::rgb(254, 243, 199));
        $borde = new Border(
            new BorderPart(Border::BOTTOM, Color::rgb(209, 213, 219), Border::WIDTH_THIN),
            new BorderPart(Border::TOP, Color::rgb(209, 213, 219), Border::WIDTH_THIN),
            new BorderPart(Border::LEFT, Color::rgb(209, 213, 219), Border::WIDTH_THIN),
            new BorderPart(Border::RIGHT, Color::rgb(209, 213, 219), Border::WIDTH_THIN),
        );
        $encabezado = (new Style())->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor(self::AZUL)
            ->setCellAlignment(CellAlignment::CENTER)->setShouldWrapText()->setBorder($borde);
        $texto = (new Style())->setBorder($borde);
        $centro = (new Style())->setBorder($borde)->setCellAlignment(CellAlignment::CENTER);
        $numero = (new Style())->setBorder($borde)->setFormat('0.00');
        $numeroNegrita = (new Style())->setBorder($borde)->setFormat('0.00')->setFontBold();
        $aprobado = (new Style())->setBorder($borde)->setFontBold()->setFontColor(Color::rgb(22, 101, 52))->setCellAlignment(CellAlignment::CENTER);
        $reprobado = (new Style())->setBorder($borde)->setFontBold()->setFontColor(Color::rgb(185, 28, 28))->setCellAlignment(CellAlignment::CENTER);

        $writer->addRow(Row::fromValues([$acta['institucion']], $titulo));
        $writer->addRow(Row::fromValues(['ACTA DE CALIFICACIONES' . ($acta['cerrado'] ? '' : ' (PROVISIONAL)')], $negrita));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(new Row([Cell::fromValue(''), Cell::fromValue('Asignatura:', $etiqueta), Cell::fromValue($acta['materia'], $negrita)]));
        $writer->addRow(new Row([Cell::fromValue(''), Cell::fromValue('Grado / Paralelo:', $etiqueta), Cell::fromValue("{$acta['grado']} \"{$acta['seccion']}\"", $negrita)]));
        $writer->addRow(new Row([Cell::fromValue(''), Cell::fromValue('Año lectivo:', $etiqueta), Cell::fromValue($acta['anio_lectivo'] ?? '—', $negrita)]));
        $writer->addRow(new Row([Cell::fromValue(''), Cell::fromValue('Docente:', $etiqueta), Cell::fromValue($acta['profesor'] ?? '—', $negrita)]));
        $writer->addRow(new Row([
            Cell::fromValue(''),
            Cell::fromValue('Estado del curso:', $etiqueta),
            Cell::fromValue($acta['cerrado'] ? "Cerrado el {$acta['fecha_cierre']}" : 'En curso', $negrita),
        ]));
        $writer->addRow(new Row([Cell::fromValue(''), Cell::fromValue('Emitido:', $etiqueta), Cell::fromValue($acta['emitido'])]));

        if (!$acta['cerrado']) {
            $writer->addRow(Row::fromValues(['Documento provisional: el curso aún no ha sido cerrado y las notas pueden cambiar.'], $aviso));
        }
        $writer->addRow(Row::fromValues([]));

        // Encabezados de la tabla
        $cabeceras = ['N.º', 'Estudiante', 'Cédula'];
        foreach ($acta['parciales'] as $p) {
            $cabeceras[] = "{$p['nombre']} (/" . $this->numeroTexto($p['nota_maxima']) . ')';
        }
        array_push($cabeceras, 'Total (/' . $this->numeroTexto($acta['nota_maxima_total']) . ')', '%', 'Estado');
        $writer->addRow(Row::fromValues($cabeceras, $encabezado)->setHeight(30));

        // Filas de estudiantes (las notas como números, no texto)
        foreach ($acta['filas'] as $fila) {
            $celdas = [
                Cell::fromValue($fila['numero'], $centro),
                Cell::fromValue($fila['estudiante'], $texto),
                Cell::fromValue($fila['identificacion'] ?? '', $centro),
            ];
            foreach ($fila['notas_parciales'] as $nota) {
                $celdas[] = Cell::fromValue($nota, $numero);
            }
            $celdas[] = Cell::fromValue($fila['total'], $numeroNegrita);
            $celdas[] = Cell::fromValue($fila['porcentaje'], $numero);
            $celdas[] = Cell::fromValue($fila['estado_texto'], match ($fila['estado']) {
                'aprobado' => $aprobado,
                'reprobado' => $reprobado,
                default => $centro,
            });
            $writer->addRow(new Row($celdas));
        }

        // Resumen
        $r = $acta['resumen'];
        $writer->addRow(Row::fromValues([]));
        foreach ([
            'Estudiantes' => $r['total'],
            'Aprobados' => $r['aprobados'],
            'Reprobados' => $r['reprobados'],
            'Sin calificaciones' => $r['sin_calificaciones'],
            'Promedio del curso (%)' => $r['promedio_porcentaje'],
        ] as $nombreDato => $valor) {
            $writer->addRow(new Row([Cell::fromValue(''), Cell::fromValue($nombreDato . ':', $etiqueta), Cell::fromValue($valor, $negrita)]));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['', "Se aprueba con el {$this->numeroTexto($acta['porcentaje_aprobacion'])} % de la nota máxima."], $gris));

        $writer->close();

        return $ruta;
    }

    private function numeroTexto(float|int|null $valor): string
    {
        if ($valor === null) {
            return '—';
        }
        return rtrim(rtrim(number_format((float) $valor, 2, ',', ''), '0'), ',');
    }
}
