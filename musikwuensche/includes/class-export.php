<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Excel export – generates a real .xlsx file (Office Open XML)
 * without external libraries by writing the necessary ZIP structure.
 */
class MW_Export {

    public static function download_xlsx() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );

        $rows = MW_Wunsch::get_all();

        $headers = array( 'Titel', 'Interpret', 'Anzahl Wünsche', 'Gewünscht von', 'Brautpaar', 'Spotify Link', 'Apple Music Link' );
        $data    = array();
        foreach ( $rows as $r ) {
            $data[] = array(
                $r->titel,
                $r->interpret,
                (int) $r->anzahl_wuensche,
                str_replace( '|', ', ', $r->wunsch_namen ?? '' ),
                $r->ist_brautpaar ? '★' : '',
                $r->spotify_url ?: '',
                $r->apple_url ?: '',
            );
        }

        $filename = 'musikwuensche-' . date( 'Y-m-d' ) . '.xlsx';
        $tmp_zip  = tempnam( sys_get_temp_dir(), 'mwxlsx' );

        $zip = new ZipArchive();
        $zip->open( $tmp_zip, ZipArchive::OVERWRITE );

        $zip->addFromString( '[Content_Types].xml', self::content_types() );
        $zip->addFromString( '_rels/.rels',          self::root_rels() );
        $zip->addFromString( 'xl/workbook.xml',      self::workbook() );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', self::workbook_rels() );
        $zip->addFromString( 'xl/styles.xml',        self::styles() );
        $zip->addFromString( 'xl/worksheets/sheet1.xml', self::sheet( $headers, $data ) );
        $zip->close();

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $tmp_zip ) );
        readfile( $tmp_zip );
        @unlink( $tmp_zip );
        exit;
    }

    private static function content_types() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    }

    private static function root_rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private static function workbook() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Musikwünsche" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';
    }

    private static function workbook_rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    }

    private static function styles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="2">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
    </fonts>
    <fills count="3">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF1B3A3A"/></patternFill></fill>
    </fills>
    <borders count="1"><border/></borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="2">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
    </cellXfs>
</styleSheet>';
    }

    private static function sheet( $headers, $data ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        // Header row
        $xml .= '<row r="1">';
        foreach ( $headers as $i => $h ) {
            $cell = self::col_letter( $i ) . '1';
            $xml .= sprintf( '<c r="%s" s="1" t="inlineStr"><is><t>%s</t></is></c>', $cell, htmlspecialchars( $h, ENT_XML1 ) );
        }
        $xml .= '</row>';

        // Data rows
        foreach ( $data as $row_idx => $row ) {
            $r_num = $row_idx + 2;
            $xml  .= '<row r="' . $r_num . '">';
            foreach ( $row as $c_idx => $val ) {
                $cell = self::col_letter( $c_idx ) . $r_num;
                if ( is_int( $val ) ) {
                    $xml .= sprintf( '<c r="%s"><v>%d</v></c>', $cell, $val );
                } else {
                    $xml .= sprintf( '<c r="%s" t="inlineStr"><is><t>%s</t></is></c>', $cell, htmlspecialchars( (string) $val, ENT_XML1 ) );
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private static function col_letter( $i ) {
        $letter = '';
        while ( $i >= 0 ) {
            $letter = chr( 65 + ( $i % 26 ) ) . $letter;
            $i = (int) ( $i / 26 ) - 1;
        }
        return $letter;
    }
}
