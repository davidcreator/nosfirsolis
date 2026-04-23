<?php

namespace System\Library;

class ExportService
{
    public function exportCsv(array $rows, array $headers = []): string
    {
        $fp = fopen('php://temp', 'w+');

        if (!empty($headers)) {
            fputcsv($fp, $headers, ';');
        }

        foreach ($rows as $row) {
            fputcsv($fp, $row, ';');
        }

        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);

        return $content === false ? '' : $content;
    }

    public function exportPdfPlaceholder(): string
    {
        return 'Exportacao PDF sera disponibilizada em extensao futura.';
    }

    public function printableHtmlWrapper(string $html): string
    {
        return '<html><head><meta charset="utf-8"><title>Impressao</title></head><body>' . $html . '</body></html>';
    }
}