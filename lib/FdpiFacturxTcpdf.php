<?php
/**
 * UNUSED FILE - This approach was abandoned in favor of subprocess injection
 * (see inject_facturx.php which runs in a separate PHP process to avoid
 * FPDF/TCPDF class conflicts). Kept for reference only.
 *
 * Version TCPDF de FdpiFacturx pour compatibilité avec Dolibarr.
 * Dolibarr utilise TCPDF, pas FPDF. La classe originale FdpiFacturx
 * étend \setasign\Fpdi\Fpdi (FPDF) ce qui crée un conflit de signatures.
 * Cette version étend \setasign\Fpdi\Tcpdf\Fpdi (TCPDF) à la place.
 */

namespace Atgp\FacturX\Fpdi;

use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfType;

class FdpiFacturxTcpdf extends \setasign\Fpdi\Tcpdf\Fpdi
{
    public const ICC_PROFILE_PATH = __DIR__.'/../vendor/atgp/factur-x/src/Fpdi/icc/sRGB2014.icc';

    protected $files = [];
    protected $metadata_descriptions = [];
    protected $file_spe_dictionnary_index = 0;
    protected $description_index = 0;
    protected $output_intent_index = 0;
    protected $n_files;
    protected $open_attachment_pane = false;
    protected $pdf_metadata_infos = [];
    protected bool $containsBinaryData = false;

    public function setPDFVersion($version = '1.7')
    {
        parent::setPDFVersion($version);
    }

    public function setContainsBinaryData(bool $containsBinaryData = false)
    {
        $this->containsBinaryData = $containsBinaryData;
    }

    public function Attach($file, $name = '', $desc = '', $relationship = 'Unspecified', $mimetype = '', $isUTF8 = false)
    {
        if ('' == $name) {
            $p = strrpos($file, '/');
            if (false === $p) {
                $p = strrpos($file, '\\');
            }
            if (false !== $p) {
                $name = substr($file, $p + 1);
            } else {
                $name = $file;
            }
        }
        if (!$isUTF8) {
            $desc = self::utf8_encode_safe($desc);
        }
        if ('' == $mimetype) {
            $mimetype = mime_content_type($file);
            if (!$mimetype) {
                $mimetype = 'application/octet-stream';
            }
        }
        $mimetype = str_replace('/', '#2F', $mimetype);
        $this->files[] = ['file' => $file, 'name' => $name, 'desc' => $desc, 'relationship' => $relationship, 'subtype' => $mimetype];
    }

    public function OpenAttachmentPane()
    {
        $this->open_attachment_pane = true;
    }

    public function AddMetadataDescriptionNode($description)
    {
        $this->metadata_descriptions[] = $description;
    }

    public function set_pdf_metadata_infos(array $pdf_metadata_infos)
    {
        $this->pdf_metadata_infos = $pdf_metadata_infos;
    }

    protected function _putheader()
    {
        parent::_putheader();
        if ($this->containsBinaryData) {
            $this->_put('%'.chr(rand(128, 255)).chr(rand(128, 255)).chr(rand(128, 255)).chr(rand(128, 255)));
        }
    }

    protected function _putfiles()
    {
        foreach ($this->files as $i => &$info) {
            $this->_put_file_specification($info);
            $info['file_index'] = $this->n;
            $this->_put_file_stream($info);
        }
        $this->_put_file_dictionary();
    }

    protected function _put_file_specification(array $file_info)
    {
        $this->_newobj();
        $this->file_spe_dictionnary_index = $this->n;
        $this->_put('<<');
        $this->_put('/F ('.$this->_escape($file_info['name']).')');
        $this->_put('/Type /Filespec');
        $this->_put('/UF '.$this->_textstring(self::utf8_encode_safe($file_info['name'])));
        if ($file_info['relationship']) {
            $this->_put('/AFRelationship /'.$file_info['relationship']);
        }
        if ($file_info['desc']) {
            $this->_put('/Desc '.$this->_textstring($file_info['desc']));
        }
        $this->_put('/EF <<');
        $this->_put('/F '.($this->n + 1).' 0 R');
        $this->_put('/UF '.($this->n + 1).' 0 R');
        $this->_put('>>');
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _put_file_stream(array $file_info)
    {
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/Filter /FlateDecode');
        if ($file_info['subtype']) {
            $this->_put('/Subtype /'.$file_info['subtype']);
        }
        $this->_put('/Type /EmbeddedFile');
        if (is_string($file_info['file']) && @is_file($file_info['file'])) {
            $fc = file_get_contents($file_info['file']);
            $md = @date('YmdHis', filemtime($file_info['file']));
        } else {
            $stream = $file_info['file']->getStream();
            \fseek($stream, 0);
            $fc = stream_get_contents($stream);
            $md = @date('YmdHis');
        }
        if (false === $fc) {
            $this->Error('Cannot open file: '.$file_info['file']);
        }
        $fc = gzcompress($fc);
        $this->_put('/Length '.strlen($fc));
        $this->_put("/Params <</ModDate (D:$md)>>");
        $this->_put('>>');
        $this->_putstream($fc);
        $this->_put('endobj');
    }

    protected function _put_file_dictionary()
    {
        $this->_newobj();
        $this->n_files = $this->n;
        $this->_put('<<');
        $s = '';
        $files = $this->files;
        usort($files, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        foreach ($files as $info) {
            $s .= sprintf('%s %s 0 R ', $this->_textstring($info['name']), $info['file_index']);
        }
        $this->_put(sprintf('/Names [%s]', $s));
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _put_metadata_descriptions()
    {
        $s = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'."\n";
        $s .= '<x:xmpmeta xmlns:x="adobe:ns:meta/">'."\n";
        $s .= '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'."\n";
        $this->_newobj();
        $this->description_index = $this->n;
        foreach ($this->metadata_descriptions as $desc) {
            $s .= $desc."\n";
        }
        $s .= '</rdf:RDF>'."\n";
        $s .= '</x:xmpmeta>'."\n";
        $s .= '<?xpacket end="w"?>';
        $this->_put('<<');
        $this->_put('/Length '.strlen($s));
        $this->_put('/Type /Metadata');
        $this->_put('/Subtype /XML');
        $this->_put('>>');
        $this->_putstream($s);
        $this->_put('endobj');
    }

    protected function _putresources()
    {
        parent::_putresources();
        if (!empty($this->files)) {
            $this->_putfiles();
        }
        $this->_putoutputintent();
        if (!empty($this->metadata_descriptions)) {
            $this->_put_metadata_descriptions();
        }
    }

    protected function _putoutputintent()
    {
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/Type /OutputIntent');
        $this->_put('/S /GTS_PDFA1');
        $this->_put('/OuputCondition (sRGB)');
        $this->_put('/OutputConditionIdentifier (Custom)');
        $this->_put('/DestOutputProfile '.($this->n + 1).' 0 R');
        $this->_put('/Info (sRGB V4 ICC)');
        $this->_put('>>');
        $this->_put('endobj');
        $this->output_intent_index = $this->n;

        $icc = file_get_contents(static::ICC_PROFILE_PATH);
        $icc = gzcompress($icc);
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/Length '.strlen($icc));
        $this->_put('/N 3');
        $this->_put('/Filter /FlateDecode');
        $this->_put('>>');
        $this->_putstream($icc);
        $this->_put('endobj');
    }

    protected function _putcatalog()
    {
        parent::_putcatalog();
        if (!empty($this->files)) {
            if (is_array($this->files)) {
                $files_ref_str = '';
                foreach ($this->files as $file) {
                    if ('' != $files_ref_str) {
                        $files_ref_str .= ' ';
                    }
                    $files_ref_str .= sprintf('%s 0 R', $file['file_index']);
                }
                $this->_put(sprintf('/AF [%s]', $files_ref_str));
            } else {
                $this->_put(sprintf('/AF %s 0 R', $this->n_files));
            }
            if (0 != $this->description_index) {
                $this->_put(sprintf('/Metadata %s 0 R', $this->description_index));
            }
            $this->_put('/Names <<');
            $this->_put('/EmbeddedFiles ');
            $this->_put(sprintf('%s 0 R', $this->n_files));
            $this->_put('>>');
        }
        if (0 != $this->output_intent_index) {
            $this->_put(sprintf('/OutputIntents [%s 0 R]', $this->output_intent_index));
        }
        if ($this->open_attachment_pane) {
            $this->_put('/PageMode /UseAttachments');
        }
    }

    protected function _puttrailer()
    {
        parent::_puttrailer();
        $created_id = md5($this->_generate_metadata_string('created'));
        $modified_id = md5($this->_generate_metadata_string('modified'));
        $this->_put(sprintf('/ID [<%s><%s>]', $created_id, $modified_id));
    }

    protected function _generate_metadata_string($date_type = 'created')
    {
        $metadata_string = '';
        if (isset($this->pdf_metadata_infos['title'])) {
            $metadata_string .= $this->pdf_metadata_infos['title'];
        }
        if (isset($this->pdf_metadata_infos['subject'])) {
            $metadata_string .= $this->pdf_metadata_infos['subject'];
        }
        switch ($date_type) {
            case 'modified':
                if (isset($this->pdf_metadata_infos['modifiedDate'])) {
                    $metadata_string .= $this->pdf_metadata_infos['modifiedDate'];
                }
                break;
            default:
                if (isset($this->pdf_metadata_infos['createdDate'])) {
                    $metadata_string .= $this->pdf_metadata_infos['createdDate'];
                }
                break;
        }
        return $metadata_string;
    }

    protected static function utf8_encode_safe($s)
    {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
        if (function_exists('iconv')) {
            return iconv('ISO-8859-1', 'UTF-8', $s);
        }
        return $s;
    }
}
