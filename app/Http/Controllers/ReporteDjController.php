<?php

namespace App\Http\Controllers;

use App\Support\ReporteDj;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class ReporteDjController extends Controller
{
    private const ESTADOS = [
        '0' => 'Pendientes', '1' => 'Autorizadas', '2' => 'Rechazadas', '3' => 'Canceladas', '4' => 'Registradas',
    ];

    public function exportar(Request $request, string $formato)
    {
        abort_unless(in_array($formato, ['pdf', 'excel'], true), 404);

        $f = $request->validate([
            'periodo' => 'required|in:' . implode(',', ReporteDj::PERIODOS),
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'estado' => 'nullable|in:0,1,2,3,4',
            'usuario' => 'nullable|string|max:100',
            'supervisor' => 'nullable|string|max:100',
            'contribuyente' => 'nullable|string|max:30',
        ]);

        $filas = ReporteDj::todos($f);
        [$desde, $hasta] = ReporteDj::rango($f['periodo'], $f['desde'] ?? '', $f['hasta'] ?? '');

        $rango = $desde === $hasta ? $desde : "{$desde} al {$hasta}";
        $estado = self::ESTADOS[$f['estado'] ?? ''] ?? 'Todos';
        $nombre = 'reporte-dj-' . now()->format('Ymd-His');

        if ($formato === 'pdf') {
            return Pdf::loadView('reportes.pdf', compact('filas', 'rango', 'estado'))
                ->setPaper('a4', 'landscape')
                ->download($nombre . '.pdf');
        }

        return response()->streamDownload(
            fn () => $this->escribirExcel($filas, $rango, $estado),
            $nombre . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    private function escribirExcel(array $filas, string $rango, string $estado): void
    {
        $azul = '0B1F66';
        $gris = 'F3F4F6';
        $borde = new Border(
            new BorderPart(BorderName::TOP, 'D4D4D8'),
            new BorderPart(BorderName::BOTTOM, 'D4D4D8'),
            new BorderPart(BorderName::LEFT, 'D4D4D8'),
            new BorderPart(BorderName::RIGHT, 'D4D4D8'),
        );

        $columnas = [
            ['N°', 8], ['Usuario', 14], ['Nombre', 30], ['Acción', 16], ['Origen', 14], ['Estado', 14],
            ['Solicitud', 20], ['Respuesta', 20], ['Espera (seg)', 13], ['Supervisor', 28],
            ['Contribuyente', 15], ['Equipo', 20], ['IP', 16], ['Observación', 36],
        ];
        $ultima = count($columnas) - 1;

        $options = new Options();
        foreach ($columnas as $i => [, $ancho]) {
            $options->setColumnWidth($ancho, $i + 1);
        }
        $options->mergeCells(0, 1, $ultima, 1);
        $options->mergeCells(0, 2, $ultima, 2);
        $options->mergeCells(0, 3, $ultima, 3);

        $writer = new Writer($options);
        $writer->openToFile('php://output');

        $titulo = (new Style())->withFontBold(true)->withFontSize(16)->withFontColor($azul)
            ->withCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $info = (new Style())->withFontSize(10)->withFontColor('52525B');
        $cabecera = (new Style())->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor($azul)
            ->withCellAlignment(CellAlignment::CENTER)->withCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->withShouldWrapText(true)->withBorder($borde);

        $writer->addRow(Row::fromValuesWithStyle([config('app.name') . ' - Reporte de Declaraciones Juradas'], $titulo, 28));
        $writer->addRow(Row::fromValuesWithStyle(["Período: {$rango}   |   Estado: {$estado}   |   Registros: " . count($filas)], $info));
        $writer->addRow(Row::fromValuesWithStyle(['Generado: ' . now()->format('d/m/Y H:i') . '   por ' . (auth()->user()->USUARI_T_NOMUSU ?? '')], $info));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle(array_column($columnas, 0), $cabecera, 24));

        $colores = [ // estado => [fondo, texto]
            0 => ['FEF3C7', '92400E'], 1 => ['DCFCE7', '166534'], 2 => ['FEE2E2', '991B1B'],
            3 => ['E4E4E7', '3F3F46'], 4 => ['DBEAFE', '1E40AF'],
        ];

        foreach ($filas as $n => $r) {
            $fondo = $n % 2 ? $gris : Color::WHITE;
            $base = (new Style())->withBackgroundColor($fondo)->withBorder($borde)
                ->withCellVerticalAlignment(CellVerticalAlignment::CENTER);
            $centro = $base->withCellAlignment(CellAlignment::CENTER);
            [$bg, $fg] = $colores[(int) $r->AUTDJ_N_ESTADO] ?? [$fondo, '000000'];
            $badge = $centro->withBackgroundColor($bg)->withFontColor($fg)->withFontBold(true);

            $valores = [
                $r->AUTDJ_N_CODIGO, $r->USUARIO, $r->USUARIO_NOMBRE, $r->AUTDJ_T_ACCION, $r->AUTDJ_T_ORIGEN,
                ucfirst(strtolower((string) $r->ESTADO_DESC)),
                substr((string) $r->AUTDJ_D_SOLICITUD, 0, 19), substr((string) $r->AUTDJ_D_RESPUESTA, 0, 19),
                $r->SEGUNDOS_ESPERA, trim($r->SUPERVISOR . ' ' . $r->SUPERVISOR_NOMBRE), $r->CONTRI_N_CONSIS,
                $r->HOSTNAME, $r->IP, $r->AUTDJ_T_OBSERV,
            ];
            $estilos = [
                $centro, $base, $base, $base, $base, $badge, $centro, $centro,
                $centro, $base, $centro, $base, $centro, $base->withShouldWrapText(true),
            ];

            $writer->addRow(new Row(array_map(
                fn ($v, $st) => Cell::fromValue($v, $st),
                $valores,
                $estilos
            )));
        }

        $writer->close();
    }
}
